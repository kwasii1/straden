<?php

namespace App\Services;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    public ?string $lastError = null;

    public function __construct(private readonly Connector $connector) {}

    private function baseUrl(): string
    {
        return sprintf(
            '%s://%s:%s',
            $this->connector->ssl_enabled ? 'https' : 'http',
            $this->connector->host,
            $this->connector->port,
        );
    }

    private function http(): PendingRequest
    {
        $client = Http::timeout($this->connector->timeout ?? 5)
            ->withOptions(['verify' => $this->connector->verify_ssl]);

        if ($this->connector->token) {
            $client = $client->withToken($this->connector->token);
        } elseif ($this->connector->username && $this->connector->password) {
            $client = $client->withBasicAuth($this->connector->username, $this->connector->password);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        $response = $this->http()->get($this->baseUrl().$path, $params);

        if ($response->failed()) {
            throw new \RuntimeException('Prometheus request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Available metrics and their metadata (type, help text, units).
     *
     * @see https://prometheus.io/docs/prometheus/latest/querying/api/#querying-metadata
     *
     * @return array<string, mixed>
     */
    public function metadata(?string $metric = null): array
    {
        $params = $metric !== null ? ['metric' => $metric] : [];

        return $this->get('/api/v1/metadata', $params);
    }

    /**
     * Instant query evaluated at an optional point in time.
     *
     * @see https://prometheus.io/docs/prometheus/latest/querying/api/#instant-queries
     *
     * @return array<string, mixed>
     */
    public function query(string $query, ?int $time = null): array
    {
        $params = ['query' => $query];

        if ($time !== null) {
            $params['time'] = $time;
        }

        return $this->get('/api/v1/query', $params);
    }

    /**
     * Range query over an inclusive [start, end] window with a step size.
     *
     * @see https://prometheus.io/docs/prometheus/latest/querying/api/#range-queries
     *
     * @return array<string, mixed>
     */
    public function queryRange(string $query, int $start, int $end, string $step): array
    {
        return $this->get('/api/v1/query_range', [
            'query' => $query,
            'start' => $start,
            'end' => $end,
            'step' => $step,
        ]);
    }

    /**
     * List all metric names (values of the __name__ label).
     *
     * @see https://prometheus.io/docs/prometheus/latest/querying/api/#querying-label-values
     *
     * @return array<int, string>
     */
    public function listMetrics(?int $limit = null): array
    {
        $data = $this->get('/api/v1/label/__name__/values');

        $metrics = $data['data'] ?? [];

        return $limit !== null ? array_slice($metrics, 0, $limit) : $metrics;
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->http()->get($this->baseUrl().'/api/v1/status/buildinfo');

            if (! $response->successful()) {
                $this->lastError = 'HTTP '.$response->status().' from '.$this->baseUrl();
            }

            return $response->successful();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Prometheus connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public static function fromPrometheusConnector(): self
    {
        return new self(Connector::ofType(ConnectorType::Prometheus)->firstOrFail());
    }

    public static function forProject(Project $project): ?self
    {
        $connector = $project->connectors()
            ->where('type', ConnectorType::Prometheus->value)
            ->orderBy('is_system', 'desc')
            ->first();

        return $connector !== null ? new self($connector) : null;
    }
}
