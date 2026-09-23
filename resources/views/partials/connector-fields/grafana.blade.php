<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="host" label="Host" placeholder="grafana.example.com" />
    <flux:input wire:model="port" label="Port" type="number" placeholder="{{ $this->portPlaceholder() }}" />
</div>

<flux:input wire:model="token" label="API Token" placeholder="Optional API token" />