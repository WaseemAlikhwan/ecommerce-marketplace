<x-guest-layout :title="__('Verify Email')">
    <x-auth.heading
        :title="__('Confirm your email')"
        :subtitle="__('Thanks for signing up. Click the link we sent, or request a new one if it did not arrive.')"
    />
    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tone="success" class="mb-6">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-ui.alert>
    @endif
    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button class="w-full">{{ __('Resend Verification Email') }}</x-primary-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-secondary-button type="submit" class="w-full">{{ __('Log Out') }}</x-secondary-button>
        </form>
    </div>
</x-guest-layout>
