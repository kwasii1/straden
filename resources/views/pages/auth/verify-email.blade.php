<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Verify your email')" :description="__('Please verify your email address by clicking on the link we just emailed to you.')" />

        @if (session('status') == 'verification-link-sent')
            <x-auth-session-status :status="__('A new verification link has been sent to the email address you provided during registration.')" />
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Resend verification email') }}
            </flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-sm text-zinc-500">
            @csrf
            {{ __('Wrong account?') }}
            <button type="submit" class="ui-link cursor-pointer" data-test="logout-button">{{ __('Log out') }}</button>
        </form>
    </div>
</x-layouts::auth>
