<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="host" label="Host" placeholder="localhost" />
    <flux:input wire:model="port" label="Port" type="number" placeholder="{{ $this->portPlaceholder() }}" />
</div>

<flux:input wire:model="token" label="Bearer Token" placeholder="Optional API token" />
<flux:description class="!mt-1">Used as a bearer token for authenticated Prometheus instances.</flux:description>