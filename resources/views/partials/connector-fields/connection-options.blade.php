<flux:switch wire:model="ssl_enabled" label="SSL Enabled" />

@if ($ssl_enabled)
    <flux:switch wire:model="verify_ssl" label="Verify SSL Certificate" />
@endif

<flux:field>
    <flux:label>Timeout (seconds)</flux:label>
    <flux:input wire:model="timeout" type="number" min="1" max="60" />
    <flux:description>Maximum time to wait for a connection response.</flux:description>
</flux:field>