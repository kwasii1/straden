<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\Run;
use Illuminate\Support\Facades\Process;

class RunProcessManager
{
    /**
     * Start a k6 run in the background and return the watchdog PID.
     *
     * The command is wrapped so the shell backgrounds k6, records k6's real
     * PID to a file (used for cancellation), waits for k6 to exit, then writes
     * its exit code to a file. The returned PID is the session-leading shell,
     * which stays alive until k6 exits and the exit code is recorded, so it
     * doubles as a liveness indicator for the polling job.
     *
     * @return array{pid: int, running: bool}
     */
    public function start(Run $run, string $scriptDir, string $targetUrl): array
    {
        $runId = $run->id;

        $k6Command = 'k6 run script.js --summary-export='.RunResultService::summaryFilePath($runId);

        $influxOutput = $this->buildInfluxOutput($run);
        if ($influxOutput !== null) {
            $k6Command .= ' '.$influxOutput;
        }

        $innerShell = $k6Command
            .' & KPID=$!; echo $KPID > '.RunResultService::k6PidFilePath($runId)
            .'; wait $KPID; echo $? > '.RunResultService::exitCodeFilePath($runId);

        $process = Process::quietly()
            ->forever()
            ->options(['create_new_console' => true])
            ->path($scriptDir)
            ->env(['TARGET_URL' => $targetUrl])
            ->start(['setsid', 'sh', '-c', $innerShell]);

        return [
            'pid' => $process->id(),
            'running' => $process->running(),
        ];
    }

    private function buildInfluxOutput(Run $run): ?string
    {
        try {
            $connector = Connector::influxDb();

            $protocol = $connector->ssl_enabled ? 'https' : 'http';
            $influxUrl = "{$protocol}://{$connector->host}:{$connector->port}/{$connector->database}";

            return sprintf(
                '--out influxdb=%s --tag run_id=%s --tag test_id=%s --tag script_id=%s',
                escapeshellarg($influxUrl),
                escapeshellarg($run->id),
                escapeshellarg($run->script->test_id),
                escapeshellarg($run->script->id),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
