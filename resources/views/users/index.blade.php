<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('User management') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded-md" role="status">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('users.index') }}" class="p-4 bg-white shadow sm:rounded-lg grid gap-4 md:grid-cols-5">
                <div>
                    <x-input-label for="search" :value="__('Search name or email')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1 block w-full" :value="request('search')" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">All statuses</option>
                        @foreach (['pending', 'active', 'suspended', 'inactive'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="division" :value="__('Division')" />
                    <select id="division" name="division" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">All divisions</option>
                        @foreach (\App\Enums\Division::cases() as $division)
                            <option value="{{ $division->value }}" @selected(request('division') === $division->value)>{{ $division->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">All roles</option>
                        @foreach (\App\Enums\Role::cases() as $role)
                            <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
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
                                <x-secondary-button>Reactivate</x-secondary-button>
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
                            <x-secondary-button>Update role</x-secondary-button>
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
                            <x-secondary-button>Update division</x-secondary-button>
                        </form>

                        <form method="POST" action="{{ route('users.password', $user) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            @method('PUT')
                            <label class="text-sm text-gray-700">
                                Temporary password
                                <input type="password" name="password" autocomplete="new-password" required class="mt-1 border-gray-300 rounded-md shadow-sm">
                            </label>
                            <label class="text-sm text-gray-700">
                                Confirm
                                <input type="password" name="password_confirmation" autocomplete="new-password" required class="mt-1 border-gray-300 rounded-md shadow-sm">
                            </label>
                            <x-secondary-button>Set password</x-secondary-button>
                        </form>
                    </div>
                </section>
            @endforeach

            <div>{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
