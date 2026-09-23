<?php

namespace App\Services;

/**
 * Collapse raw k6 `name` tag values into route patterns.
 *
 * k6 tags each request with the full URL by default, so one dynamic route
 * (e.g. GET /todos/:id) fans out into hundreds of distinct tag values. This
 * groups those back into a single pattern so endpoint filters stay usable.
 *
 * @phpstan-type EndpointGroup array{pattern: string, label: string, count: int, names: array<int, string>}
 */
class EndpointGrouper
{
    /**
     * Normalize a raw endpoint name into its route pattern.
     *
     * Dynamic path segments (integers, UUIDs, ObjectIds, long tokens) become
     * `{id}`; everything else is kept verbatim. Non-URL values pass through.
     */
    public static function normalize(string $name): string
    {
        if (! preg_match('#^https?://#i', $name)) {
            return $name;
        }

        $parts = parse_url($name);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $name;
        }

        $segments = array_values(array_filter(
            explode('/', $parts['path'] ?? ''),
            fn (string $segment) => $segment !== ''
        ));

        $normalized = array_map(
            fn (string $segment) => self::isDynamicSegment($segment) ? '{id}' : $segment,
            $segments
        );

        $pattern = ($parts['scheme'] ?? 'http').'://'.$parts['host'];

        if (isset($parts['port'])) {
            $pattern .= ':'.$parts['port'];
        }

        $pattern .= '/'.implode('/', $normalized);

        if (isset($parts['query'])) {
            $pattern .= '?'.$parts['query'];
        }

        return $pattern;
    }

    /**
     * Group raw endpoint names by their route pattern.
     *
     * Static routes (one URL per pattern) sort first, then groups by request
     * count descending, so the most significant routes surface at the top.
     *
     * @param  array<int, string>  $names
     * @return array<int, EndpointGroup>
     */
    public static function group(array $names): array
    {
        $unique = array_values(array_unique($names));
        $groups = [];

        foreach ($unique as $name) {
            $pattern = self::normalize($name);

            if (! isset($groups[$pattern])) {
                $groups[$pattern] = [
                    'pattern' => $pattern,
                    'label' => self::label($pattern),
                    'count' => 0,
                    'names' => [],
                ];
            }

            $groups[$pattern]['count']++;
            $groups[$pattern]['names'][] = $name;
        }

        $groups = array_values($groups);

        usort($groups, function (array $a, array $b): int {
            $aSingle = $a['count'] === 1 ? 0 : 1;
            $bSingle = $b['count'] === 1 ? 0 : 1;

            if ($aSingle !== $bSingle) {
                return $aSingle <=> $bSingle;
            }

            return $b['count'] <=> $a['count'];
        });

        return $groups;
    }

    /**
     * Memoized normalize() results, keyed by raw name. Grouped selections
     * re-normalize thousands of names on every chart query — without this,
     * a 9k-URL group would re-run the segment regexes ~60k times per page.
     *
     * @var array<string, string>
     */
    private static array $patternCache = [];

    /**
     * Build an anchored InfluxQL regex matching every name behind a pattern.
     *
     * Used instead of enumerating thousands of `"name"='...'` OR terms, which
     * blows past URL length limits on the InfluxDB query API (GET) and makes
     * the request fail. The `{id}` matcher mirrors isDynamicSegment() exactly,
     * so the regex covers precisely the names that normalize to the pattern.
     */
    public static function toRegex(string $pattern): string
    {
        if (! preg_match('#^https?://#i', $pattern)) {
            return '/^'.preg_quote($pattern, '/').'$/';
        }

        $parts = parse_url($pattern);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return '/^'.preg_quote($pattern, '/').'$/';
        }

        $segments = array_values(array_filter(
            explode('/', $parts['path'] ?? ''),
            fn (string $segment) => $segment !== ''
        ));

        $regex = preg_quote(($parts['scheme'] ?? 'http').'://'.$parts['host'], '/');

        if (isset($parts['port'])) {
            $regex .= ':'.preg_quote((string) $parts['port'], '/');
        }

        foreach ($segments as $segment) {
            $regex .= '\/'.($segment === '{id}' ? self::ID_SEGMENT_REGEX : preg_quote($segment, '/'));
        }

        if (isset($parts['query'])) {
            $regex .= '\?'.preg_quote($parts['query'], '/');
        }

        return '/^'.$regex.'$/';
    }

    /**
     * Regex for one `{id}` path segment. Mirrors isDynamicSegment() so
     * toRegex() covers exactly the names behind a pattern. Kept
     * RE2-compatible (no lookaheads) for InfluxQL.
     */
    private const ID_SEGMENT_REGEX = '(?:[0-9]+|[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}|[0-9A-Fa-f]{24}|[A-Za-z0-9_-]{20,})';

    /**
     * Regex matching a set of raw names, or null when they do not share one
     * pattern (mixed/stale selections fall back to OR enumeration).
     *
     * @param  array<int, string>  $names
     */
    public static function regexForNames(array $names): ?string
    {
        $unique = array_values(array_unique($names));

        if ($unique === []) {
            return null;
        }

        $pattern = self::memoizedNormalize($unique[0]);

        foreach ($unique as $name) {
            if (self::memoizedNormalize($name) !== $pattern) {
                return null;
            }
        }

        return self::toRegex($pattern);
    }

    private static function memoizedNormalize(string $name): string
    {
        return self::$patternCache[$name] ??= self::normalize($name);
    }

    /**
     * Short display label for a pattern (last two path segments).
     */
    public static function label(string $pattern): string
    {
        if (! preg_match('#^https?://#i', $pattern)) {
            return $pattern;
        }

        $segments = array_values(array_filter(
            explode('/', (string) parse_url($pattern, PHP_URL_PATH)),
            fn (string $segment) => $segment !== ''
        ));

        if (count($segments) >= 2) {
            return implode('/', array_slice($segments, -2));
        }

        return $segments[0] ?? $pattern;
    }

    /**
     * Determine whether a path segment is a dynamic identifier.
     */
    private static function isDynamicSegment(string $segment): bool
    {
        // Pure integers: /todos/123
        if (preg_match('/^\d+$/', $segment)) {
            return true;
        }

        // UUIDs: 550e8400-e29b-41d4-a716-446655440000
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment)) {
            return true;
        }

        // Mongo ObjectIds: 24 hex chars.
        if (preg_match('/^[0-9a-f]{24}$/i', $segment)) {
            return true;
        }

        // Long opaque tokens (CUID / ULID / nanoid): 20+ alphanumerics,
        // e.g. cm3k9x2p10000 or 01ARZ3NDEKTSV4RRFFQ69G5FAV. Deliberately
        // without a digit requirement so normalize() and the toRegex()
        // `{id}` matcher agree exactly on what counts as an identifier.
        if (strlen($segment) >= 20 && preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
            return true;
        }

        return false;
    }
}
