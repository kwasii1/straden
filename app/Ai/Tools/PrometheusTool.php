<?php

namespace App\Ai\Tools;

use App\Models\Connector;
use App\Services\PrometheusService;
use Laravel\Ai\Contracts\Tool;

abstract class PrometheusTool implements Tool
{
    public function __construct(private readonly ?Connector $connector) {}

    protected function service(): ?PrometheusService
    {
        return $this->connector !== null ? new PrometheusService($this->connector) : null;
    }

    protected function unavailable(): string
    {
        return json_encode([
            'available' => false,
            'error' => 'No Prometheus connector is configured for this project. Add one on the Connectors page.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    protected function failure(\Throwable $e): string
    {
        return json_encode([
            'available' => false,
            'error' => 'Failed to reach Prometheus: '.$e->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
