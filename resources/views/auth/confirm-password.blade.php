<x-guest-layout :title="__('Confirm Password')">
    <x-auth.heading
        :title="__('Confirm your password')"
        :subtitle="__('This is a secure area. Please confirm your password before continuing.')"
    />
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>
        <x-primary-button class="w-full">{{ __('Confirm') }}</x-primary-button>
    </form>
</x-guest-layout>
