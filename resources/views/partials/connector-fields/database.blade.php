<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="host" label="Host" placeholder="localhost" />
    <flux:input wire:model="port" label="Port" type="number" placeholder="{{ $this->portPlaceholder() }}" />
</div>

<flux:input wire:model="database" label="Database" placeholder="my_database" />

<div class="grid grid-cols-2 gap-4">
    <flux:input wire:model="username" label="Username" placeholder="username" />
    <flux:input wire:model="password" label="Password" type="password" placeholder="Password" />
</div>