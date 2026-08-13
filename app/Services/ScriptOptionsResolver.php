<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ScriptOptionsResolver
{
    public function __construct(private string $source) {}

    /**
     * Build a resolver from a script file stored on the local disk.
     */
    public static function fromStorage(string $path): ?self
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        return new self((string) $disk->get($path));
    }

    /**
     * Extract the raw `options` object literal from the script source.
     */
    public function optionsBlock(): ?string
    {
        if (! preg_match('/\boptions\s*=\s*\{/', $this->source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $match[0][1] + strpos($match[0][0], '{');
        $depth = 0;

        for ($i = $start, $length = strlen($this->source); $i < $length; $i++) {
            $char = $this->source[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($this->source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Resolve the total run duration in seconds from `duration` or `stages`.
     */
    public function durationSeconds(): ?int
    {
        $block = $this->optionsBlock();

        if ($block === null) {
            return null;
        }

        if (preg_match('/\bstages\s*:\s*\[/', $block, $match, PREG_OFFSET_CAPTURE)) {
            $stages = $this->arrayBlock($block, strpos($block, '[', $match[0][1]));

            if ($stages === null) {
                return null;
            }

            $total = 0;

            foreach ($this->stageDurations($stages) as $duration) {
                $seconds = self::parseDurationString($duration);

                if ($seconds === null) {
                    return null;
                }

                $total += $seconds;
            }

            return $total > 0 ? $total : null;
        }

        if (preg_match('/\bduration\s*:\s*[\'"]([^\'"]+)[\'"]/', $block, $match)) {
            return self::parseDurationString($match[1]);
        }

        return null;
    }

    public function vus(): ?int
    {
        $block = $this->optionsBlock();

        if ($block === null) {
            return null;
        }

        if (preg_match('/\bvus\s*:\s*(\d+)/', $block, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    public function iterations(): ?int
    {
        $block = $this->optionsBlock();

        if ($block === null) {
            return null;
        }

        if (preg_match('/\biterations\s*:\s*(\d+)/', $block, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * Snapshot of the k6 options used to configure a run.
     *
     * @return array<string, mixed>
     */
    public function toRunConfig(): array
    {
        return array_filter([
            'vus' => $this->vus(),
            'duration_seconds' => $this->durationSeconds(),
            'iterations' => $this->iterations(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Extract a balanced `[...]` block starting at the given offset.
     */
    private function arrayBlock(string $source, int $start): ?string
    {
        $depth = 0;

        for ($i = $start, $length = strlen($source); $i < $length; $i++) {
            $char = $source[$i];

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function stageDurations(string $stages): array
    {
        preg_match_all('/\bduration\s*:\s*[\'"]([^\'"]+)[\'"]/', $stages, $matches);

        return $matches[1] ?? [];
    }

    private static function parseDurationString(string $duration): ?int
    {
        if (! preg_match_all('/(\d+(?:\.\d+)?)(ms|s|m|h|d)/', $duration, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $total = 0.0;

        foreach ($matches as $match) {
            $value = (float) $match[1];

            $total += match ($match[2]) {
                'ms' => $value / 1000,
                's' => $value,
                'm' => $value * 60,
                'h' => $value * 3600,
                'd' => $value * 86400,
                default => 0.0,
            };
        }

        return $total > 0 ? (int) round($total) : null;
    }
}
