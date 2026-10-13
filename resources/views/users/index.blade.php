<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('User management') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md" role="status">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('users.index') }}" class="p-4 bg-white shadow sm:rounded-lg flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="search" :value="__('Search name or email')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1 block w-full" :value="request('search')" />
                </div>
                <div class="flex items-end gap-2">
                    <x-primary-button>{{ __('Search') }}</x-primary-button>
                    <a class="text-sm underline text-blue-700" href="{{ route('users.index') }}">Clear</a>
                </div>
            </form>

            @foreach ($users as $user)
                <section class="p-4 sm:p-6 bg-white shadow sm:rounded-lg space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $user->full_name }}</h3>
                            <p class="text-sm text-gray-600">{{ $user->email }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $user->division->value }} · {{ $user->role->value }} ·
                                {{ ! $user->is_active ? 'Inactive' : ucfirst($user->status->value) }}
                            </p>
                        </div>
                        @if ($user->signature_path)
                            <a class="text-sm underline text-blue-700" href="{{ route('users.signature', $user) }}" target="_blank" rel="noopener">View signature</a>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @if ($user->status === \App\Enums\UserStatus::Pending)
                            <form method="POST" action="{{ route('users.approve', $user) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button>Approve</x-primary-button>
                            </form>
                        @endif

                        @if ($user->status === \App\Enums\UserStatus::Active && $user->is_active)
                            <form method="POST" action="{{ route('users.suspend', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-4 py-2 bg-red-700 text-white rounded-md text-xs font-semibold uppercase">Suspend</button>
                            </form>
                        @elseif ($user->status !== \App\Enums\UserStatus::Pending)
                            <form method="POST" action="{{ route('users.reactivate', $user) }}">
                                @csrf
                                @method('PATCH')
                                <x-secondary-button type="submit">Reactivate</x-secondary-button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('users.role', $user) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <label class="text-sm text-gray-700">
                                Role
                                <select name="role" class="ms-1 border-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Enums\Role::cases() as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->value }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <x-secondary-button type="submit">Update role</x-secondary-button>
                        </form>

                        <form method="POST" action="{{ route('users.division', $user) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            @method('PATCH')
                            <label class="text-sm text-gray-700">
                                Division
                                <select name="division" class="ms-1 border-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Enums\Division::cases() as $division)
                                        <option value="{{ $division->value }}" @selected($user->division === $division)>{{ $division->value }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <x-secondary-button type="submit">Update division</x-secondary-button>
                        </form>

                        <form method="POST" action="{{ route('users.password', $user) }}" class="reset-password-form">
                            @csrf
                            @method('PUT')
                            <x-secondary-button type="submit" data-user-name="{{ $user->full_name }}">Reset password</x-secondary-button>
                        </form>
                    </div>
                </section>
            @endforeach

            <div>{{ $users->links() }}</div>
        </div>
    </div>

    <div id="password-reset-banner" class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-50 rounded-md p-4 shadow-lg" role="alert"></div>

    <dialog id="password-reset-dialog" class="w-full max-w-md rounded-lg p-6 shadow-xl backdrop:bg-gray-900/50">
        <h2 class="text-lg font-semibold text-gray-900">Temporary password</h2>
        <p class="mt-2 text-sm text-gray-600">Give this password to the employee. It will only be shown once, and they must change it when they sign in.</p>
        <code id="password-reset-value" class="mt-4 block break-all rounded bg-gray-100 p-3 text-lg text-gray-900"></code>
        <div class="mt-4 flex justify-end gap-2">
            <button id="password-reset-copy" type="button" class="px-4 py-2 border border-blue-200 rounded-md text-xs font-semibold uppercase tracking-widest text-blue-800 hover:bg-blue-50">Copy</button>
            <button id="password-reset-close" type="button" class="px-4 py-2 bg-blue-700 rounded-md text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-800">Done</button>
        </div>
    </dialog>

    <script>
        (() => {
            const banner = document.getElementById('password-reset-banner');
            const dialog = document.getElementById('password-reset-dialog');
            const passwordValue = document.getElementById('password-reset-value');

            const showBanner = (message, isError = false) => {
                banner.textContent = message;
                banner.className = `fixed top-4 left-1/2 -translate-x-1/2 z-50 rounded-md p-4 shadow-lg ${isError ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800'}`;
            };

            document.querySelectorAll('.reset-password-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"]');
                    const name = button.dataset.userName;

                    if (!window.confirm(`Reset password for ${name}?`)) return;

                    button.disabled = true;
                    banner.classList.add('hidden');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: { Accept: 'application/json' },
                            body: new FormData(form),
                            credentials: 'same-origin',
                        });
                        const result = await response.json();

                        if (!response.ok) throw new Error(result.message || 'Unable to reset this password.');

                        passwordValue.textContent = result.password;
                        dialog.showModal();
                    } catch (error) {
                        showBanner(error.message, true);
                    } finally {
                        button.disabled = false;
                    }
                });
            });

            document.getElementById('password-reset-copy').addEventListener('click', async () => {
                await navigator.clipboard.writeText(passwordValue.textContent);
                showBanner('Temporary password copied.');
            });

            const clearPassword = () => { passwordValue.textContent = ''; };
            document.getElementById('password-reset-close').addEventListener('click', () => dialog.close());
            dialog.addEventListener('close', clearPassword);
        })();
    </script>
</x-app-layout>
