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
        try {
            $success = match ($connector->type) {
                ConnectorType::InfluxDb => (new InfluxDbService($connector))->testConnection(),
                ConnectorType::Prometheus => (new PrometheusService($connector))->testConnection(),
                ConnectorType::MySQL, ConnectorType::Postgres, ConnectorType::MongoDB => (new DatabaseMetricsService($connector))->testConnection(),
                ConnectorType::Redis => (new RedisMetricsService($connector))->testConnection(),
                ConnectorType::Grafana => (new GrafanaService($connector))->testConnection(),
                default => null,
            };
        } catch (\Throwable) {
            $success = false;
        }

        if ($success === null) {
            Flux::toast(variant: 'warning', text: 'No connection test available for this connector.');

            return;
        }

        $connector->update([
            'last_tested_at' => now(),
            'last_test_successful' => $success,
            'last_test_error' => $success ? null : 'Connection failed.',
        ]);

        if ($success) {
            Flux::toast(variant: 'success', text: 'Connection successful.');
        } else {
            Flux::toast(variant: 'error', text: 'Connection failed.');
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

<div class="flex flex-col gap-8">
    @php
        $connectorTypeOptions = [
            ['value' => 'prometheus', 'label' => 'Prometheus', 'description' => 'Metrics queried with PromQL', 'icon' => 'chart-bar-square'],
            ['value' => 'grafana', 'label' => 'Grafana', 'description' => 'Dashboards and data sources', 'icon' => 'presentation-chart-bar'],
            ['value' => 'mysql', 'label' => 'MySQL', 'description' => 'Database server metrics', 'icon' => 'circle-stack'],
            ['value' => 'postgres', 'label' => 'PostgreSQL', 'description' => 'Database server metrics', 'icon' => 'circle-stack'],
            ['value' => 'mongodb', 'label' => 'MongoDB', 'description' => 'Database server metrics', 'icon' => 'cube'],
            ['value' => 'redis', 'label' => 'Redis', 'description' => 'Cache server metrics', 'icon' => 'bolt'],
            ['value' => 'repository', 'label' => 'Repository', 'description' => 'Source code for context', 'icon' => 'folder-git-2'],
        ];
    @endphp

    <x-page-header title="Connectors" description="Connect external services that provide data for analysis, monitoring and the agent.">
        <x-slot:actions>
            <flux:button wire:click="newConnector" variant="primary" icon="plus">Add connector</flux:button>
        </x-slot:actions>
    </x-page-header>

    <section class="ui-panel overflow-hidden">
        @if ($this->connectors->isEmpty())
            <x-empty-state icon="link" title="No connectors yet" description="Add a connector to pull metrics from Prometheus, Grafana or a database. InfluxDB is set up automatically.">
                <flux:button wire:click="newConnector" size="sm" icon="plus">Add connector</flux:button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Host</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->connectors as $connector)
                            <tr wire:key="connector-{{ $connector->id }}">
                                <td>
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200 ring-inset">
                                            <flux:icon :name="$this->connectorIcon($connector)" variant="micro" />
                                        </span>
                                        <span class="truncate font-medium text-zinc-900">{{ $connector->name }}</span>
                                        @if ($connector->is_system)
                                            <flux:badge size="sm" color="zinc">System</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $connector->type->label() }}</td>
                                <td class="max-w-64 truncate font-mono text-xs text-zinc-500">
                                    @if ($connector->host)
                                        {{ $connector->host }}@if ($connector->port):{{ $connector->port }}@endif
                                    @else
                                        <span class="font-sans text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($connector->last_tested_at)
                                        @if ($connector->last_test_successful)
                                            <x-status-badge status="connected" />
                                        @else
                                            <x-status-badge status="failed" />
                                        @endif
                                    @else
                                        <x-status-badge status="untested" label="Not tested" />
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button wire:click="testConnection('{{ $connector->id }}')" variant="ghost" size="sm">
                                            Test
                                        </flux:button>

                                        @unless ($connector->is_system)
                                            <button
                                                type="button"
                                                wire:click="editConnector('{{ $connector->id }}')"
                                                class="ui-icon-button"
                                                aria-label="Edit connector"
                                                title="Edit"
                                            >
                                                <flux:icon.pencil-square variant="micro" />
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="confirmDelete('{{ $connector->id }}')"
                                                class="ui-icon-button hover:bg-red-50 hover:text-red-600"
                                                aria-label="Remove connector"
                                                title="Remove"
                                            >
                                                <flux:icon.trash variant="micro" />
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <flux:modal name="create-connector" class="md:w-lg" flyout>
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ $editingConnectorId ? 'Edit connector' : 'Add connector' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingConnectorId ? 'Update this connector. Leave credentials blank to keep the current values.' : 'Connect an external service to Straden.' }}</flux:text>
            </div>

            <form wire:submit="addConnector" class="flex flex-col gap-6">
                <flux:input wire:model="name" label="Name" placeholder="My database" />

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-zinc-800">Type</legend>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($connectorTypeOptions as $option)
                            @php $isSelectedType = $type === $option['value']; @endphp
                            <label
                                wire:key="connector-type-{{ $option['value'] }}"
                                @class([
                                    'ui-tile flex items-start gap-3 p-4 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-500/70',
                                    'cursor-pointer' => ! $editingConnectorId,
                                    'pointer-events-none opacity-50' => $editingConnectorId && ! $isSelectedType,
                                ])
                                @if ($isSelectedType) data-selected @endif
                            >
                                <input
                                    type="radio"
                                    wire:model.live="type"
                                    value="{{ $option['value'] }}"
                                    class="sr-only"
                                    @disabled((bool) $editingConnectorId)
                                />
                                <flux:icon :name="$option['icon']" variant="mini" class="mt-0.5 shrink-0 text-zinc-500" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-zinc-900">{{ $option['label'] }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $option['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <flux:error name="type" />
                </fieldset>

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

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ $editingConnectorId ? 'Save changes' : 'Add connector' }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="delete-connector" class="md:w-[28rem]">
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Remove connector?</flux:heading>
                <flux:text class="mt-1">Tests will no longer be able to use this connector. This can't be undone.</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteConnector" variant="danger">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
