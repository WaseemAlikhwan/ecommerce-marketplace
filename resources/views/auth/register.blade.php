<x-guest-layout :title="__('Register')">
    <x-auth.heading
        :title="__('Create your account')"
        :subtitle="__('Email and phone must be unique. Vendor applications come after email verification.')"
    />

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>
        <div>
            <x-input-label for="phone" :value="__('Phone')" />
            <x-text-input id="phone" type="text" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="+9639xxxxxxxx" dir="ltr" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>
        </div>
        <x-primary-button class="w-full">{{ __('Create an account') }}</x-primary-button>
    </form>

    <p class="mt-10 text-center text-sm text-ink-muted">
        {{ __('Already registered?') }}
        <a href="{{ route('login') }}" class="ms-1 text-ink underline decoration-line underline-offset-4">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
