<?php

namespace App\Services;

use App\Models\Connector;
use Illuminate\Support\Facades\Http;

class GrafanaService
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

    public function testConnection(): bool
    {
        try {
            $client = Http::timeout($this->connector->timeout ?? 5)
                ->withOptions(['verify' => $this->connector->verify_ssl]);

            if ($this->connector->token) {
                $client = $client->withToken($this->connector->token);
            }

            $response = $client->get($this->baseUrl().'/api/health');

            return $response->successful() && ($response->json('database') ?? 'ok') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }
}
