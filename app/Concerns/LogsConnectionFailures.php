<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait LogsConnectionFailures
{
    /**
     * Record why a connection test failed so it can be diagnosed from the logs.
     */
    protected function logConnectionFailure(string $reason): void
    {
        Log::warning('Connector connection test failed', [
            'connector' => $this->connector->name,
            'type' => $this->connector->type->value,
            'host' => $this->connector->connectionHost(),
            'port' => $this->connector->port,
            'reason' => $reason,
        ]);
    }

    protected function logConnectionException(Throwable $exception): void
    {
        $this->logConnectionFailure($exception->getMessage());
    }
}
