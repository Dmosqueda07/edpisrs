<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">{{ __('Profile signature') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">Upload a PNG or JPG signature image. Maximum size: 1 MB.</p>
                    </header>

                    @if (auth()->user()->signature_path)
                        <img class="mt-4 max-h-32 border rounded bg-white p-2" src="{{ route('users.signature', auth()->user()) }}" alt="Your saved signature">
                    @endif

                    @if (session('status') === 'Signature uploaded.')
                        <p class="mt-2 text-sm text-green-700" role="status">{{ session('status') }}</p>
                    @endif

                    <form method="POST" action="{{ route('profile.signature.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="signature" :value="__('Signature image')" />
                            <input id="signature" name="signature" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" required class="mt-1 block w-full">
                            <x-input-error class="mt-2" :messages="$errors->get('signature')" />
                        </div>
                        <x-primary-button>{{ __('Upload signature') }}</x-primary-button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
