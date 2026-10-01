<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Services\DatabaseMetricsService;
use App\Services\GrafanaService;
use App\Services\InfluxDbService;
use App\Services\PrometheusService;
use App\Services\RedisMetricsService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;
use Livewire\Component;

new
#[Layout('layouts::main-app')]
class extends Component
{
    public Project $project;

    public string $name = '';

    public string $type = '';

    public string $host = '';

    public ?int $port = null;

    public ?string $database = null;

    public bool $ssl_enabled = false;

    public bool $verify_ssl = false;

    public int $timeout = 5;

    public string $username = '';

    public string $password = '';

    public string $token = '';

    public array $settings = [];

    public ?string $editingConnectorId = null;

    public ?string $deletingConnectorId = null;

    #[Computed]
    public function connectors()
    {
        return Connector::query()
            ->where(fn ($query) => $query
                ->where('project_id', $this->project->id)
                ->orWhere('is_system', true))
            ->orderBy('is_system', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function addConnector(): void
    {
        $this->validate($this->connectorRules());

        $data = [
            'project_id' => $this->project->id,
            'name' => $this->name,
            'type' => ConnectorType::from($this->type),
            'host' => $this->host ?: null,
            'port' => $this->port,
            'database' => $this->database !== '' && $this->database !== null ? $this->database : null,
            'ssl_enabled' => $this->ssl_enabled,
            'verify_ssl' => $this->verify_ssl,
            'timeout' => $this->timeout,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'token' => $this->token ?: null,
            'settings' => $this->settings,
        ];

        if ($this->editingConnectorId !== null) {
            $connector = $this->findEditableConnector($this->editingConnectorId);

            $data = collect($data)->except(['project_id', 'type'])->all();
            foreach (['username', 'password', 'token'] as $secret) {
                if ($data[$secret] === null) {
                    unset($data[$secret]);
                }
            }

            $connector->update($data);
            $message = 'Connector updated successfully.';
        } else {
            Connector::create($data);
            $message = 'Connector added successfully.';
        }

        Flux::modal('create-connector')->close();

        Flux::toast(variant: 'success', text: $message);

        $this->resetConnectorForm();
    }

    public function newConnector(): void
    {
        $this->resetValidation();
        $this->resetConnectorForm();
        $this->editingConnectorId = null;

        Flux::modal('create-connector')->show();
    }

    public function editConnector(string $connectorId): void
    {
        $connector = $this->findEditableConnector($connectorId);

        $this->resetValidation();
        $this->editingConnectorId = $connector->id;
        $this->name = $connector->name;
        $this->type = $connector->type->value;
        $this->host = $connector->host ?? '';
        $this->port = $connector->port;
        $this->database = $connector->database;
        $this->ssl_enabled = $connector->ssl_enabled;
        $this->verify_ssl = $connector->verify_ssl;
        $this->timeout = $connector->timeout ?? 5;
        $this->username = '';
        $this->password = '';
        $this->token = '';
        $this->settings = $connector->settings ?? [];

        Flux::modal('create-connector')->show();
    }

    public function confirmDelete(string $connectorId): void
    {
        $this->deletingConnectorId = $connectorId;

        Flux::modal('delete-connector')->show();
    }

    public function deleteConnector(): void
    {
        $connector = Connector::query()
            ->where('project_id', $this->project->id)
            ->find($this->deletingConnectorId);

        Flux::modal('delete-connector')->close();
        $this->deletingConnectorId = null;

        if ($connector === null) {
            return;
        }

        if ($connector->is_system) {
            Flux::toast(variant: 'error', text: 'System connectors cannot be deleted.');

            return;
        }

        $connector->delete();

        Flux::toast(variant: 'success', text: 'Connector removed.');
    }

    public function testConnection(Connector $connector): void
    {
        $error = null;

        try {
            $success = match ($connector->type) {
                ConnectorType::InfluxDb => (new InfluxDbService($connector))->testConnection(),
                ConnectorType::Prometheus => (function () use ($connector, &$error) {
                    $service = new PrometheusService($connector);
                    $result = $service->testConnection();
                    $error = $service->lastError;

                    return $result;
                })(),
                ConnectorType::MySQL, ConnectorType::Postgres, ConnectorType::MongoDB => (new DatabaseMetricsService($connector))->testConnection(),
                ConnectorType::Redis => (new RedisMetricsService($connector))->testConnection(),
                ConnectorType::Grafana => (new GrafanaService($connector))->testConnection(),
                default => null,
            };
        } catch (\Throwable $e) {
            $success = false;
            $error = $e->getMessage();
        }

        if ($success === null) {
            Flux::toast(variant: 'warning', text: 'No connection test available for this connector.');

            return;
        }

        $connector->update([
            'last_tested_at' => now(),
            'last_test_successful' => $success,
            'last_test_error' => $success ? null : ($error ?: 'Connection failed.'),
        ]);

        if ($success) {
            Flux::toast(variant: 'success', text: 'Connection successful.');
        } else {
            Flux::toast(variant: 'error', text: Str::limit($error ?: 'Connection failed.', 200));
        }
    }

    public function updatedType(): void
    {
        $this->reset(['host', 'port', 'database', 'ssl_enabled', 'verify_ssl', 'username', 'password', 'token', 'settings']);
        $this->timeout = 5;

        $type = ConnectorType::tryFrom($this->type);

        if ($type?->defaultPort() !== null) {
            $this->port = $type->defaultPort();
        }
    }

    public function portPlaceholder(): string
    {
        return (string) (ConnectorType::tryFrom($this->type)?->defaultPort() ?? 5432);
    }

    public function connectorIcon(Connector $connector): string
    {
        return match ($connector->type) {
            ConnectorType::InfluxDb => 'chart-bar',
            ConnectorType::Prometheus => 'chart-bar-square',
            ConnectorType::MySQL, ConnectorType::Postgres, ConnectorType::Database => 'circle-stack',
            ConnectorType::MongoDB => 'cube',
            ConnectorType::Redis => 'bolt',
            ConnectorType::Repository => 'folder-git-2',
            ConnectorType::Grafana => 'presentation-chart-bar',
            default => 'link',
        };
    }

    private function connectorRules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:prometheus,mysql,postgres,mongodb,redis,repository,grafana',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'nullable|string|max:255',
            'ssl_enabled' => 'boolean',
            'verify_ssl' => 'boolean',
            'timeout' => 'integer|min:1|max:60',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:1024',
        ];

        return match ($this->type) {
            'repository' => $rules,
            'redis' => [
                ...$rules,
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
                'database' => 'nullable|integer|min:0|max:15',
            ],
            'mysql', 'postgres', 'mongodb', 'prometheus', 'grafana' => [
                ...$rules,
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
            ],
            default => [...$rules, 'host' => 'nullable|string|max:255'],
        };
    }

    private function findEditableConnector(string $connectorId): Connector
    {
        return Connector::query()
            ->where('project_id', $this->project->id)
            ->where('is_system', false)
            ->findOrFail($connectorId);
    }

    private function resetConnectorForm(): void
    {
        $this->reset(['editingConnectorId', 'name', 'type', 'host', 'port', 'database', 'ssl_enabled', 'verify_ssl', 'timeout', 'username', 'password', 'token', 'settings']);
        $this->timeout = 5;
    }
};
?>

<div class="flex flex-col gap-y-10">
    <div class="flex flex-col">
        <flux:heading size="xl">Connectors</flux:heading>
        <flux:text>Connect external services to provide data for analysis, monitoring, and AI-assisted operations.</flux:text>
    </div>

    <div class="flex w-full justify-end">
        <flux:button wire:click="newConnector" variant="primary" icon="plus">Add Connector</flux:button>
    </div>

    <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
        @forelse ($this->connectors as $connector)
            <div wire:key="connector-{{ $connector->id }}" class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                <div class="flex items-center gap-x-4">
                    <div class="flex items-center gap-x-3">
                        <flux:icon :name="$this->connectorIcon($connector)" class="size-5 text-zinc-400" />

                        <div>
                            <div class="flex items-center gap-x-2">
                                <flux:heading class="font-medium">{{ $connector->name }}</flux:heading>
                                @if ($connector->is_system)
                                    <flux:badge size="sm" variant="subtle" color="purple">System</flux:badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-x-2 mt-0.5">
                                <flux:badge size="sm" variant="subtle" color="zinc">
                                    {{ $connector->type->label() }}
                                </flux:badge>

                                @if ($connector->host)
                                    <flux:text class="text-xs">{{ $connector->host }}@if ($connector->port):{{ $connector->port }}@endif</flux:text>
                                @endif

                                @if ($connector->last_tested_at)
                                    @if ($connector->last_test_successful)
                                        <flux:badge size="sm" variant="subtle" color="emerald">Connected</flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="subtle" color="red" :title="$connector->last_test_error">Failed</flux:badge>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-x-2">
                    <flux:button
                        wire:click="testConnection('{{ $connector->id }}')"
                        variant="ghost"
                        size="sm"
                        icon="arrow-path"
                    >
                        Test
                    </flux:button>

                    @unless ($connector->is_system)
                        <flux:button
                            wire:click="editConnector('{{ $connector->id }}')"
                            variant="ghost"
                            size="sm"
                            icon="pencil-square"
                        />

                        <flux:button
                            wire:click="confirmDelete('{{ $connector->id }}')"
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            class="text-red-500 hover:text-red-600"
                        />
                    @endunless
                </div>
            </div>
        @empty
            <div class="flex h-40 w-full items-center justify-center">
                <div class="flex flex-col items-center gap-y-2">
                    <flux:icon.link class="size-10 text-zinc-400" />
                    <flux:text class="text-center">
                        No connectors configured.
                    </flux:text>
                    <flux:text class="text-center text-sm">
                        Add a connector to get started. InfluxDB is seeded automatically.
                    </flux:text>
                </div>
            </div>
        @endforelse
    </div>

    <flux:modal name="create-connector" class="md:w-lg" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingConnectorId ? 'Edit Connector' : 'Add Connector' }}</flux:heading>
                <flux:text class="mt-2">{{ $editingConnectorId ? 'Update this connector. Leave credentials blank to keep the current values.' : 'Connect an external service to Straden.' }}</flux:text>
            </div>

            <form wire:submit="addConnector" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Connector Name" placeholder="My Database" />

                    <flux:select wire:model.live="type" label="Connector Type" :disabled="(bool) $editingConnectorId">
                        <flux:select.option value="">Select a type...</flux:select.option>
                        <flux:select.option value="prometheus">Prometheus</flux:select.option>
                        <flux:select.option value="mysql">MySQL</flux:select.option>
                        <flux:select.option value="postgres">PostgreSQL</flux:select.option>
                        <flux:select.option value="mongodb">MongoDB</flux:select.option>
                        <flux:select.option value="redis">Redis</flux:select.option>
                        <flux:select.option value="repository">Repository</flux:select.option>
                        <flux:select.option value="grafana">Grafana</flux:select.option>
                    </flux:select>
                </div>

                @if ($type === 'prometheus')
                    @include('partials.connector-fields.prometheus')
                    @include('partials.connector-fields.connection-options')
                @elseif (in_array($type, ['mysql', 'postgres', 'mongodb'], true))
                    @include('partials.connector-fields.database')
                    @include('partials.connector-fields.connection-options')
                @elseif ($type === 'redis')
                    @include('partials.connector-fields.redis')
                    @include('partials.connector-fields.connection-options')
                @elseif ($type === 'grafana')
                    @include('partials.connector-fields.grafana')
                    @include('partials.connector-fields.connection-options')
                @elseif ($type === 'repository')
                    @include('partials.connector-fields.repository')
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ $editingConnectorId ? 'Save Changes' : 'Add Connector' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="delete-connector" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Remove connector?</flux:heading>
                <flux:text class="mt-2">This connector will be removed and can no longer be used by tests. This cannot be undone.</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteConnector" variant="danger">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
