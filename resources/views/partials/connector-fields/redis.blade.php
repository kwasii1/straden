<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="host" label="Host" placeholder="localhost" />
    <flux:input wire:model="port" label="Port" type="number" placeholder="{{ $this->portPlaceholder() }}" />
</div>

<flux:field>
    <flux:label>Database Index</flux:label>
    <flux:input wire:model="database" type="number" min="0" placeholder="0" />
    <flux:description>Redis logical database index (0-15).</flux:description>
</flux:field>

<flux:input wire:model="password" label="Password" type="password" placeholder="Optional password" />