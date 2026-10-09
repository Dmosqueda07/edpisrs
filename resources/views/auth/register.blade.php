<x-guest-layout>
    <div class="mb-4">
        <p class="text-sm text-gray-600">Register for an employee account. An administrator must approve your account before you can sign in.</p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="first_name" :value="__('First name')" />
            <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autofocus autocomplete="given-name" />
            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
        </div>

        <div>
            <x-input-label for="last_name" :value="__('Last name')" />
            <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required autocomplete="family-name" />
            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required maxlength="225" autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="division" :value="__('Division')" />
            <select id="division" name="division" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Select division</option>
                @foreach (\App\Enums\Division::cases() as $division)
                    <option value="{{ $division->value }}" @selected(old('division') === $division->value)>{{ $division->value }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('division')" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="underline text-sm text-blue-700 hover:text-blue-900" href="{{ route('login') }}">Already registered?</a>
            <x-primary-button>{{ __('Register') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
