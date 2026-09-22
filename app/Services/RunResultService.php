<?php

namespace App\Services;

use App\Models\Run;
use App\Notifications\RunCompleted;
use Illuminate\Support\Facades\Storage;

class RunResultService
{
    public static function summaryFilePath(string $runId): string
    {
        return sys_get_temp_dir().'/k6-summary-'.$runId.'.json';
    }

    public static function exitCodeFilePath(string $runId): string
    {
        return sys_get_temp_dir().'/k6-exit-'.$runId.'.txt';
    }

    public static function k6PidFilePath(string $runId): string
    {
        return sys_get_temp_dir().'/k6-pid-'.$runId.'.txt';
    }

    public static function logFilePath(string $runId): string
    {
        return sys_get_temp_dir().'/k6-log-'.$runId.'.log';
    }

    public static function persistedLogPath(string $runId): string
    {
        return Storage::disk('local')->path('run-logs/'.$runId.'.log');
    }

    public static function hasLog(string $runId): bool
    {
        return file_exists(self::logFilePath($runId))
            || Storage::disk('local')->exists('run-logs/'.$runId.'.log');
    }

    /**
     * Read the next chunk of the run log starting at the given byte offset.
     *
     * Prefers the live temp log while the run is active, falling back to the
     * persisted log once the run has finished.
     *
     * @return array{content: string, nextOffset: int, eof: bool}
     */
    public static function readLogChunk(string $runId, int $offset, int $maxBytes = 262144): array
    {
        $path = self::currentLogPath($runId);

        if ($path === null) {
            return ['content' => '', 'nextOffset' => $offset, 'eof' => true];
        }

        $size = (int) filesize($path);

        if ($offset >= $size) {
            return ['content' => '', 'nextOffset' => $offset, 'eof' => true];
        }

        $offset = max(0, $offset);

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['content' => '', 'nextOffset' => $offset, 'eof' => true];
        }

        fseek($handle, $offset);
        $content = (string) fread($handle, min($size - $offset, $maxBytes));
        fclose($handle);

        $nextOffset = $offset + strlen($content);

        return [
            'content' => $content,
            'nextOffset' => $nextOffset,
            'eof' => $nextOffset >= $size,
        ];
    }

    private static function currentLogPath(string $runId): ?string
    {
        $tempPath = self::logFilePath($runId);

        if (file_exists($tempPath)) {
            return $tempPath;
        }

        $persistedPath = self::persistedLogPath($runId);

        return file_exists($persistedPath) ? $persistedPath : null;
    }

    public static function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $stat = @file_get_contents('/proc/'.$pid.'/stat');

        if ($stat === false) {
            return posix_kill($pid, 0);
        }

        $closeParen = strrpos($stat, ')');

        if ($closeParen === false) {
            return false;
        }

        $fields = preg_split('/\s+/', trim(substr($stat, $closeParen + 1)));

        return ($fields[0] ?? '') !== 'Z';
    }

    /**
     * Stop a running k6 process. The status update is applied by the polling
     * job once the process has exited.
     */
    public static function cancel(Run $run): void
    {
        if ($run->status === 'running') {
            $k6Pid = self::readK6Pid($run->id);

            if ($k6Pid !== null) {
                @posix_kill($k6Pid, SIGTERM);
            }

            return;
        }

        if ($run->status === 'queued') {
            $run->update([
                'status' => 'aborted',
                'completed_at' => now(),
                'duration_seconds' => 0,
            ]);

            self::notifyCompletion($run);
        }
    }

    public static function finalize(Run $run): void
    {
        if ($run->status !== 'running') {
            return;
        }

        $runId = $run->id;

        $exitCode = self::readExitCode($runId);
        $summary = self::readSummary($runId);

        $update = [
            'completed_at' => now(),
            'duration_seconds' => $run->started_at
                ? (int) max(0, abs(now()->diffInSeconds($run->started_at)))
                : null,
            'exit_code' => $exitCode,
        ];

        if (is_array($summary) && isset($summary['metrics'])) {
            $update = array_merge($update, self::parseSummary($summary));
        }

        $logTail = self::readLogTail($runId);

        // A teardown timeout kills an otherwise clean run after the load has
        // finished: k6 exits 101, but every recorded request succeeded. The
        // load results are valid — only cleanup died — so this is a pass with
        // a warning, not an error.
        $teardownTimeout = $exitCode === 101
            && $logTail !== null
            && str_contains($logTail, 'teardown() execution timed out');

        $cleanLoad = ($update['requests_total'] ?? 0) > 0
            && ($update['error_rate'] ?? 100) == 0;

        $teardownWarning = $teardownTimeout && $cleanLoad;

        $update['status'] = match (true) {
            $exitCode === 0 => 'passed',
            $teardownWarning => 'passed',
            in_array($exitCode, [99, 104], true) => 'failed',
            $exitCode === 108 => 'error',
            in_array($exitCode, [130, 137, 143], true) => 'aborted',
            $exitCode === null => 'error',
            default => 'error',
        };

        // k6's exit code is the authoritative threshold result: it only exits 0
        // when every threshold is met. Only record it when thresholds exist.
        // For a teardown-timeout pass, derive it from the evaluated summary.
        $update['thresholds_passed'] = match (true) {
            empty($update['thresholds_summary'] ?? null) => null,
            $exitCode === 0 => true,
            in_array($exitCode, [99, 104], true) => false,
            $teardownWarning => array_all(
                $update['thresholds_summary'],
                fn ($threshold) => ($threshold['ok'] ?? false) === true,
            ),
            default => null,
        };

        if ($teardownWarning) {
            $message = 'Teardown timed out after the load completed with 0% failed requests — the results are valid, but cleanup may be incomplete (leftover test data). Batch cleanup requests with http.batch() or raise teardownTimeout.';

            if ($logTail !== null) {
                $message .= "\n\n".$logTail;
            }

            $update['error_message'] = mb_substr($message, 0, 65535);
        } elseif ($update['status'] === 'error') {
            $message = $exitCode !== null
                ? 'k6 exited with code '.$exitCode
                : 'k6 process ended without recording an exit code';

            if ($logTail !== null) {
                $message .= "\n\n".$logTail;
            }

            $update['error_message'] = mb_substr($message, 0, 65535);
        }

        $run->update($update);

        self::notifyCompletion($run);

        @unlink(self::summaryFilePath($runId));
        @unlink(self::exitCodeFilePath($runId));
        @unlink(self::k6PidFilePath($runId));

        self::persistOrDiscardLog($run);
    }

    private static function persistOrDiscardLog(Run $run): void
    {
        $tempPath = self::logFilePath($run->id);

        if (! file_exists($tempPath)) {
            return;
        }

        $run->loadMissing('script.test.project');

        if ((bool) $run->script?->test?->project?->persist_run_logs) {
            Storage::disk('local')->put('run-logs/'.$run->id.'.log', (string) file_get_contents($tempPath));
        }

        @unlink($tempPath);
    }

    private static function notifyCompletion(Run $run): void
    {
        $user = $run->triggeredByUser;

        if ($user) {
            $user->notify(new RunCompleted($run->id));
        }
    }

    private static function readExitCode(string $runId): ?int
    {
        $path = self::exitCodeFilePath($runId);

        if (! file_exists($path)) {
            return null;
        }

        $value = (int) trim((string) file_get_contents($path));

        return $value;
    }

    private static function readK6Pid(string $runId): ?int
    {
        $path = self::k6PidFilePath($runId);

        if (! file_exists($path)) {
            return null;
        }

        $value = (int) trim((string) file_get_contents($path));

        return $value > 0 ? $value : null;
    }

    private static function readSummary(string $runId): ?array
    {
        $path = self::summaryFilePath($runId);

        if (! file_exists($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function readLogTail(string $runId): ?string
    {
        $path = self::logFilePath($runId);

        if (! file_exists($path)) {
            return null;
        }

        $content = trim((string) file_get_contents($path));

        if ($content === '') {
            return null;
        }

        if (strlen($content) > 4000) {
            $content = '...'.substr($content, -4000);
        }

        return $content;
    }

    private static function parseSummary(array $summary): array
    {
        $metrics = $summary['metrics'] ?? [];

        $metric = fn (string $key, string $prop = 'value') => $metrics[$key][$prop] ?? null;

        $vusMax = $metric('vus_max');
        $requestsTotal = $metric('http_reqs', 'count');
        $requestsPerSecond = $metric('http_reqs', 'rate');
        $reqDurationP95 = $metric('http_req_duration', 'p(95)');
        $reqDurationP99 = $metric('http_req_duration', 'p(99)');

        $failedData = $metrics['http_req_failed'] ?? [];
        // For k6's `http_req_failed` rate metric, `passes` counts samples with
        // value > 0 (failed requests) and `fails` counts samples equal to 0
        // (successful requests), so the error rate is passes / total.
        $failedPasses = $failedData['passes'] ?? 0;
        $failedFails = $failedData['fails'] ?? 0;
        $failedTotal = $failedPasses + $failedFails;
        $errorRate = $failedTotal > 0 ? ($failedPasses / $failedTotal) * 100 : 0;

        $checks = self::collectChecks($summary['root_group'] ?? []);
        $checksTotal = $checks['passes'] + $checks['fails'];
        $checksFailed = $checks['fails'];

        $thresholdsPassed = null;
        $thresholdsSummary = null;

        $thresholds = [];
        foreach ($metrics as $metricName => $data) {
            if (empty($data['thresholds']) || ! is_array($data['thresholds'])) {
                continue;
            }

            foreach ($data['thresholds'] as $condition => $result) {
                $thresholds[] = [
                    'name' => $metricName,
                    'condition' => $condition,
                    'ok' => self::evaluateThreshold($condition, $data),
                    'value' => self::thresholdValue($data, $condition),
                ];
            }
        }
        $thresholdsSummary = $thresholds;

        return array_filter([
            'vus_max' => is_numeric($vusMax) ? (int) $vusMax : null,
            'requests_total' => is_numeric($requestsTotal) ? (int) $requestsTotal : null,
            'requests_per_second' => is_numeric($requestsPerSecond) ? round((float) $requestsPerSecond, 2) : null,
            'req_duration_p95_ms' => is_numeric($reqDurationP95) ? round((float) $reqDurationP95, 2) : null,
            'req_duration_p99_ms' => is_numeric($reqDurationP99) ? round((float) $reqDurationP99, 2) : null,
            'error_rate' => round($errorRate, 2),
            'checks_total' => $checksTotal > 0 ? $checksTotal : null,
            'checks_failed' => $checksTotal > 0 ? $checksFailed : null,
            'thresholds_summary' => $thresholdsSummary,
        ], fn ($v) => $v !== null);
    }

    /**
     * Evaluate a k6 threshold condition against the summary metric values.
     *
     * k6's `--summary-export` reports the `thresholds` map values incorrectly
     * (always false), so we evaluate the condition ourselves against the
     * reliable metric data. Time metric values are already exported in ms,
     * matching the units used in threshold expressions.
     */
    public static function evaluateThreshold(string $condition, array $data): bool
    {
        foreach (preg_split('/\|\|/', $condition) as $orPart) {
            if (self::evaluateAndExpression($orPart, $data)) {
                return true;
            }
        }

        return false;
    }

    private static function evaluateAndExpression(string $expression, array $data): bool
    {
        foreach (preg_split('/&&/', $expression) as $part) {
            if (! self::evaluateSingleCondition(trim($part), $data)) {
                return false;
            }
        }

        return true;
    }

    private static function evaluateSingleCondition(string $expression, array $data): bool
    {
        if (! preg_match('/^\s*(!?)\s*([A-Za-z0-9_().]+)\s*(<=|>=|==|!=|<|>)\s*([+-]?\d*\.?\d+)\s*$/', $expression, $matches)) {
            return false;
        }

        [, $negated, $property, $operator, $threshold] = $matches;

        $actual = $data[$property] ?? $data['value'] ?? null;

        if ($actual === null || ! is_numeric($actual)) {
            return false;
        }

        $result = match ($operator) {
            '<' => (float) $actual < (float) $threshold,
            '>' => (float) $actual > (float) $threshold,
            '<=' => (float) $actual <= (float) $threshold,
            '>=' => (float) $actual >= (float) $threshold,
            '==' => (float) $actual == (float) $threshold,
            '!=' => (float) $actual != (float) $threshold,
            default => false,
        };

        return $negated === '!' ? ! $result : $result;
    }

    /**
     * Extract the metric value a threshold evaluated against, when possible.
     *
     * k6 does not expose a per-threshold value in its summary, so we pull the
     * property referenced by the condition (e.g. "p(95)" from "p(95)<500")
     * directly from the metric's data, falling back to the metric's "value".
     */
    private static function thresholdValue(array $data, string $condition): mixed
    {
        $expression = preg_split('/\|\||&&/', $condition)[0] ?? $condition;

        if (preg_match('/^\s*([A-Za-z0-9_().]+)/', $expression, $matches)) {
            $property = $matches[1];

            if (array_key_exists($property, $data)) {
                return $data[$property];
            }
        }

        return $data['value'] ?? null;
    }

    private static function collectChecks(array $group): array
    {
        $passes = 0;
        $fails = 0;

        foreach ($group['checks'] ?? [] as $check) {
            $passes += $check['passes'] ?? 0;
            $fails += $check['fails'] ?? 0;
        }

        foreach ($group['groups'] ?? [] as $subGroup) {
            $sub = self::collectChecks($subGroup);
            $passes += $sub['passes'];
            $fails += $sub['fails'];
        }

        return ['passes' => $passes, 'fails' => $fails];
    }
}
