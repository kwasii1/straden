<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="w-full"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Enter one of your emergency recovery codes to access your account.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 flex flex-col gap-5">
                @csrf

                <div x-show="!showRecoveryInput" x-ref="otp">
                    <flux:otp
                        x-model="code"
                        length="6"
                        name="code"
                        label="OTP Code"
                        label:sr-only
                    />
                </div>

                <div x-show="showRecoveryInput" class="flex flex-col gap-2">
                    <flux:input
                        type="text"
                        name="recovery_code"
                        x-ref="recovery_code"
                        x-bind:required="showRecoveryInput"
                        autocomplete="one-time-code"
                        x-model="recovery_code"
                        :aria-label="__('Recovery code')"
                    />

                    @error('recovery_code')
                        <flux:text color="red">
                            {{ $message }}
                        </flux:text>
                    @enderror
                </div>

                <flux:button
                    variant="primary"
                    type="submit"
                    class="w-full"
                >
                    {{ __('Continue') }}
                </flux:button>

                <p class="text-sm text-zinc-500">
                    <span x-show="!showRecoveryInput">
                        {{ __('Lost your device?') }}
                        <button type="button" class="ui-link cursor-pointer" @click="toggleInput()">{{ __('Use a recovery code') }}</button>
                    </span>
                    <span x-show="showRecoveryInput">
                        {{ __('Have your device?') }}
                        <button type="button" class="ui-link cursor-pointer" @click="toggleInput()">{{ __('Use an authentication code') }}</button>
                    </span>
                </p>
            </form>
        </div>
    </div>
</x-layouts::auth>
