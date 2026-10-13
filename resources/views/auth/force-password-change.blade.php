<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Your administrator reset your password. Choose a new password to continue.
    </div>

    <form method="POST" action="{{ route('password.change.update') }}">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <div class="mt-4 flex justify-end">
            <x-primary-button>{{ __('Change password') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
