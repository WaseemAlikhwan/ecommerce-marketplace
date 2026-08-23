<x-guest-layout :title="__('Log in')">
    <x-auth.heading
        :title="__('Welcome back')"
        :subtitle="__('Sign in with email. One account for shopping — and, later, selling.')"
    />

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>
        <div>
            <div class="mb-1.5 flex items-center justify-between gap-3">
                <x-input-label for="password" :value="__('Password')" class="mb-0" />
                @if (Route::has('password.request'))
                    <a class="text-caption text-ink-muted hover:text-ink" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>
        <label for="remember_me" class="inline-flex items-center gap-2">
            <x-ui.checkbox id="remember_me" name="remember" />
            <span class="text-sm text-ink-muted">{{ __('Remember me') }}</span>
        </label>
        <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
    </form>

    <p class="mt-10 text-center text-sm text-ink-muted">
        {{ __('New to Sham Market?') }}
        <a href="{{ route('register') }}" class="ms-1 text-ink underline decoration-line underline-offset-4">{{ __('Create an account') }}</a>
    </p>
</x-guest-layout>
