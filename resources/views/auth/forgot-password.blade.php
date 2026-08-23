<x-guest-layout :title="__('Forgot your password?')">
    <x-auth.heading
        :title="__('Reset your password')"
        :subtitle="__('Enter the email on your account and we will send a reset link.')"
    />
    <x-auth-session-status class="mb-6" :status="session('status')" />
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>
        <x-primary-button class="w-full">{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>
    <p class="mt-10 text-center"><a href="{{ route('login') }}" class="ds-link">{{ __('Back to sign in') }}</a></p>
</x-guest-layout>
