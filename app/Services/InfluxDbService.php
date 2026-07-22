<?php

namespace App\Services;

use App\Models\Connector;
use Illuminate\Support\Facades\Http;

class InfluxDbService
{
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

    public function query(string $influxQl): array
    {
        $response = Http::timeout($this->connector->timeout ?? 5)
            ->withOptions(['verify' => $this->connector->verify_ssl])
            ->get($this->baseUrl().'/query', [
                'db' => $this->connector->database,
                'q' => $influxQl,
                'epoch' => 'ms',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('InfluxDB query failed: '.$response->body());
        }

        $json = $response->json();

        return $json['results'] ?? [];
    }

    public function showDatabases(): array
    {
        $results = $this->query('SHOW DATABASES');

        if (empty($results[0]['series'][0]['values'])) {
            return [];
        }

        return array_column($results[0]['series'][0]['values'], 0);
    }

    public function write(array $points, ?string $precision = null): void
    {
        $body = implode("\n", $points);

        $queryParams = ['db' => $this->connector->database];
        if ($precision) {
            $queryParams['precision'] = $precision;
        }

        $response = Http::timeout($this->connector->timeout ?? 5)
            ->withOptions(['verify' => $this->connector->verify_ssl])
            ->withBody($body, 'text/plain')
            ->post($this->baseUrl().'/write?'.http_build_query($queryParams));

        if ($response->failed()) {
            throw new \RuntimeException('InfluxDB write failed: '.$response->body());
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout($this->connector->timeout ?? 5)
                ->withOptions(['verify' => $this->connector->verify_ssl])
                ->get($this->baseUrl().'/ping');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function fromInfluxDbConnector(): self
    {
        return new self(Connector::influxDb());
    }
}
