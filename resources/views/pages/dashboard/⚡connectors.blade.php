<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\Project;
use App\Services\InfluxDbService;
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
    public string $database = '';
    public bool $ssl_enabled = false;
    public bool $verify_ssl = false;
    public int $timeout = 5;
    public string $username = '';
    public string $password = '';
    public string $token = '';
    public array $settings = [];

    #[Computed]
    public function connectors()
    {
        return Connector::query()
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
            'database' => $this->database ?: null,
            'ssl_enabled' => $this->ssl_enabled,
            'verify_ssl' => $this->verify_ssl,
            'timeout' => $this->timeout,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'token' => $this->token ?: null,
            'settings' => $this->settings,
        ];

        Connector::create($data);

        Flux::modal('create-connector')->close();

        Flux::toast(variant: 'success', text: 'Connector added successfully.');

        $this->resetConnectorForm();
    }

    public function deleteConnector(Connector $connector): void
    {
        if ($connector->is_system) {
            Flux::toast(variant: 'error', text: 'System connectors cannot be deleted.');

            return;
        }

        $connector->delete();

        Flux::toast(variant: 'success', text: 'Connector removed.');
    }

    public function testConnection(Connector $connector): void
    {
        $service = new InfluxDbService($connector);

        $success = $service->testConnection();

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
        $this->reset(['host', 'port', 'database', 'ssl_enabled', 'verify_ssl', 'timeout', 'username', 'password', 'token', 'settings']);
        $this->timeout = 5;
    }

    private function connectorRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:database,repository,grafana',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'nullable|string|max:255',
            'ssl_enabled' => 'boolean',
            'verify_ssl' => 'boolean',
            'timeout' => 'integer|min:1|max:60',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:1024',
        ];
    }

    private function resetConnectorForm(): void
    {
        $this->reset(['name', 'type', 'host', 'port', 'database', 'ssl_enabled', 'verify_ssl', 'timeout', 'username', 'password', 'token', 'settings']);
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
        <flux:modal.trigger name="create-connector">
            <flux:button variant="primary" icon="plus">Add Connector</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="flex flex-col border rounded-xl divide-y dark:border-zinc-700 overflow-hidden">
        @forelse ($this->connectors as $connector)
            <div wire:key="connector-{{ $connector->id }}" class="flex items-center justify-between p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                <div class="flex items-center gap-x-4">
                    <div class="flex items-center gap-x-3">
                        @if ($connector->type === App\Enums\ConnectorType::InfluxDb)
                            <flux:icon.chart-bar class="size-5 text-zinc-400" />
                        @elseif ($connector->type === App\Enums\ConnectorType::Database)
                            <flux:icon.circle-stack class="size-5 text-zinc-400" />
                        @elseif ($connector->type === App\Enums\ConnectorType::Repository)
                            <flux:icon.folder-git-2 class="size-5 text-zinc-400" />
                        @elseif ($connector->type === App\Enums\ConnectorType::Grafana)
                            <flux:icon.presentation-chart-bar class="size-5 text-zinc-400" />
                        @else
                            <flux:icon.link class="size-5 text-zinc-400" />
                        @endif

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
                                        <flux:badge size="sm" variant="subtle" color="red">Failed</flux:badge>
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
                            wire:click="deleteConnector('{{ $connector->id }}')"
                            wire:confirm="Are you sure you want to remove this connector?"
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

    <flux:modal name="create-connector" class="md:w-1/2">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Connector</flux:heading>
                <flux:text class="mt-2">Connect an external service to Straden.</flux:text>
            </div>

            <form wire:submit="addConnector" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Connector Name" placeholder="My Database" />

                    <flux:select wire:model.live="type" label="Connector Type">
                        <flux:select.option value="">Select a type...</flux:select.option>
                        <flux:select.option value="database">Database</flux:select.option>
                        <flux:select.option value="repository">Repository</flux:select.option>
                        <flux:select.option value="grafana">Grafana</flux:select.option>
                    </flux:select>
                </div>

                @if ($type)
                    <flux:input wire:model="host" label="Host" placeholder="localhost" />

                    <flux:input wire:model="port" label="Port" type="number" placeholder="5432" />

                    <flux:input wire:model="database" label="Database" placeholder="my_database" />

                    <flux:input wire:model="username" label="Username" placeholder="username" />

                    <flux:input wire:model="password" label="Password" type="password" placeholder="Password" />

                    <flux:input wire:model="token" label="Token" placeholder="Optional API token" />

                    <flux:switch wire:model="ssl_enabled" label="SSL Enabled" />

                    @if ($ssl_enabled)
                        <flux:switch wire:model="verify_ssl" label="Verify SSL Certificate" />
                    @endif

                    <flux:field>
                        <flux:label>Timeout (seconds)</flux:label>
                        <flux:input wire:model="timeout" type="number" min="1" max="60" />
                        <flux:description>Maximum time to wait for a connection response.</flux:description>
                    </flux:field>
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Add Connector</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
