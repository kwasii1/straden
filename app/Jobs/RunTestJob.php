<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Models\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class RunTestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public Run $run,
    ) {}

    public function handle(): void
    {
        $this->run->loadMissing('script.test');

        $script = $this->run->script;
        $scriptDir = Storage::disk('local')->path('scripts/'.$script->test_id.'/'.$script->id);
        $summaryFile = sys_get_temp_dir().'/k6-summary-'.$this->run->id.'.json';

        $this->run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $targetUrl = $script->test->target_url;

            $k6Command = "k6 run script.js --summary-export={$summaryFile}";

            $influxOutput = $this->buildInfluxOutput();
            if ($influxOutput !== null) {
                $k6Command .= ' '.$influxOutput;
            }

            $result = Process::timeout(600)
                ->path($scriptDir)
                ->env(['TARGET_URL' => $targetUrl])
                ->run($k6Command);

            $exitCode = $result->exitCode();
            $duration = (int) floor(microtime(true) - $this->run->started_at->timestamp);

            $update = [
                'completed_at' => now(),
                'duration_seconds' => $duration,
                'exit_code' => $exitCode,
            ];

            if (file_exists($summaryFile)) {
                $summary = json_decode(file_get_contents($summaryFile), true);

                if (is_array($summary) && isset($summary['metrics'])) {
                    $update = array_merge($update, $this->parseSummary($summary));
                }
            }

            $update['status'] = match (true) {
                $exitCode === 0 => 'passed',
                $exitCode === 99, $exitCode === 104 => 'failed',
                $exitCode === 108 => 'error',
                default => 'error',
            };

            if ($update['status'] === 'error' && $exitCode !== 108) {
                $update['error_message'] = $result->errorOutput() ?: 'k6 exited with code '.$exitCode;
            }

            if ($update['status'] === 'error' && $exitCode === 108) {
                $update['error_message'] = mb_substr($result->errorOutput(), 0, 65535);
            }

            $this->run->update($update);
        } catch (\Throwable $e) {
            $this->run->update([
                'status' => 'error',
                'completed_at' => now(),
                'duration_seconds' => (int) floor(microtime(true) - strtotime((string) $this->run->started_at)),
                'error_message' => mb_substr($e->getMessage(), 0, 65535),
            ]);

            throw $e;
        } finally {
            @unlink($summaryFile);
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->run->script_id))
                ->releaseAfter(30),
        ];
    }

    public function uniqueId(): string
    {
        return 'run-test-'.$this->run->script_id;
    }

    private function buildInfluxOutput(): ?string
    {
        try {
            $connector = Connector::influxDb();

            $protocol = $connector->ssl_enabled ? 'https' : 'http';
            $influxUrl = "{$protocol}://{$connector->host}:{$connector->port}/{$connector->database}";

            return sprintf(
                '--out influxdb=%s --tag run_id=%s --tag test_id=%s --tag script_id=%s',
                escapeshellarg($influxUrl),
                escapeshellarg($this->run->id),
                escapeshellarg($this->run->script->test_id),
                escapeshellarg($this->run->script->id),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSummary(array $summary): array
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

        $checks = $this->collectChecks($summary['root_group'] ?? []);
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

    private function collectChecks(array $group): array
    {
        $passes = 0;
        $fails = 0;

        foreach ($group['checks'] ?? [] as $check) {
            $passes += $check['passes'] ?? 0;
            $fails += $check['fails'] ?? 0;
        }

        foreach ($group['groups'] ?? [] as $subGroup) {
            $sub = $this->collectChecks($subGroup);
            $passes += $sub['passes'];
            $fails += $sub['fails'];
        }

        return ['passes' => $passes, 'fails' => $fails];
    }
}
