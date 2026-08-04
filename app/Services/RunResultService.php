<?php

namespace App\Services;

use App\Models\Run;

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

        $update['status'] = match (true) {
            $exitCode === 0 => 'passed',
            in_array($exitCode, [99, 104], true) => 'failed',
            $exitCode === 108 => 'error',
            in_array($exitCode, [130, 137, 143], true) => 'aborted',
            $exitCode === null => 'error',
            default => 'error',
        };

        if ($update['status'] === 'error' && $exitCode !== null) {
            $update['error_message'] = 'k6 exited with code '.$exitCode;
        }

        $run->update($update);

        @unlink(self::summaryFilePath($runId));
        @unlink(self::exitCodeFilePath($runId));
        @unlink(self::k6PidFilePath($runId));
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
        $failedPasses = $failedData['passes'] ?? 0;
        $failedFails = $failedData['fails'] ?? 0;
        $failedTotal = $failedPasses + $failedFails;
        $errorRate = $failedTotal > 0 ? ($failedFails / $failedTotal) * 100 : 0;

        $checks = self::collectChecks($summary['root_group'] ?? []);
        $checksTotal = $checks['passes'] + $checks['fails'];
        $checksFailed = $checks['fails'];

        $thresholdsPassed = null;
        $thresholdsSummary = null;

        $thresholds = [];
        foreach ($metrics as $name => $data) {
            if (! empty($data['thresholds'])) {
                foreach ($data['thresholds'] as $thName => $th) {
                    $thresholds[] = [
                        'name' => $thName,
                        'ok' => $th['ok'] ?? false,
                    ];
                }
            }
        }
        $thresholdsSummary = $thresholds;
        $thresholdsPassed = ! empty($thresholds) && collect($thresholds)->every(fn ($t) => $t['ok']);

        return array_filter([
            'vus_max' => is_numeric($vusMax) ? (int) $vusMax : null,
            'requests_total' => is_numeric($requestsTotal) ? (int) $requestsTotal : null,
            'requests_per_second' => is_numeric($requestsPerSecond) ? round((float) $requestsPerSecond, 2) : null,
            'req_duration_p95_ms' => is_numeric($reqDurationP95) ? round((float) $reqDurationP95, 2) : null,
            'req_duration_p99_ms' => is_numeric($reqDurationP99) ? round((float) $reqDurationP99, 2) : null,
            'error_rate' => round($errorRate, 2),
            'checks_total' => $checksTotal > 0 ? $checksTotal : null,
            'checks_failed' => $checksTotal > 0 ? $checksFailed : null,
            'thresholds_passed' => $thresholdsPassed,
            'thresholds_summary' => $thresholdsSummary,
        ], fn ($v) => $v !== null);
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
