<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('User management') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-green-800" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800" role="alert">
                    <p class="font-semibold">Please correct the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="user-management-banner" class="hidden rounded-md p-4" role="alert" aria-live="assertive"></div>

            @if ($pendingCount > 0)
                <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-blue-900" role="status">
                    {{ $pendingCount }} {{ \Illuminate\Support\Str::plural('account', $pendingCount) }} waiting for approval.
                </div>
            @endif

            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow sm:rounded-lg">
                <div class="w-full max-w-md">
                    <x-input-label for="search" :value="__('Search name or email')" />
                    <x-text-input id="search" name="search" type="search" class="mt-1 block w-full" :value="request('search')" />
                </div>
                <div class="flex items-center gap-2">
                    <x-primary-button>{{ __('Search') }}</x-primary-button>
                    <a class="text-sm underline text-blue-700" href="{{ route('users.index') }}">Clear</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-blue-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">Name and email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">Division</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-700">Registered</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($users as $user)
                            @php
                                $actor = auth()->user();
                                $allowedRoles = collect(\App\Enums\Role::cases())
                                    ->filter(fn ($role) => $actor->can('changeRole', [$user, $role->value]));
                                $canChangeDivision = $actor->can('changeDivision', $user);
                                $canApprove = $actor->can('approve', $user);
                                $canSuspend = $user->status === \App\Enums\UserStatus::Active
                                    && $user->is_active
                                    && $actor->can('suspend', $user);
                                $canReactivate = $user->status !== \App\Enums\UserStatus::Pending
                                    && ($user->status !== \App\Enums\UserStatus::Active || ! $user->is_active)
                                    && $actor->can('reactivate', $user);
                                $canResetPassword = $actor->can('resetPassword', $user);
                                $canViewSignature = (bool) $user->signature_path && $actor->can('viewSignature', $user);
                                $canManage = $canApprove || $canSuspend || $canReactivate
                                    || $allowedRoles->isNotEmpty() || $canChangeDivision
                                    || $canResetPassword || $canViewSignature;
                                $statusLabel = $user->status === \App\Enums\UserStatus::Suspended
                                    ? 'Suspended'
                                    : (! $user->is_active ? 'Inactive' : ucfirst($user->status->value));
                                $statusClass = match ($statusLabel) {
                                    'Pending' => 'bg-amber-100 text-amber-800',
                                    'Active' => 'bg-green-100 text-green-800',
                                    'Suspended' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                                $initials = \Illuminate\Support\Str::upper(
                                    \Illuminate\Support\Str::substr($user->first_name, 0, 1)
                                    .\Illuminate\Support\Str::substr($user->last_name, 0, 1)
                                );
                            @endphp
                            <tr data-user-row="{{ $user->user_id }}">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $user->full_name }}</div>
                                    <div class="text-sm text-gray-600">{{ $user->email }}</div>
                                </td>
                                <td data-user-division class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $user->division->value }}</td>
                                <td data-user-role class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ $user->role->value }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $user->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($canManage)
                                        <button type="button" class="open-user-manage inline-flex rounded-md border border-blue-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-blue-800 shadow-sm hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" data-template-id="manage-user-{{ $user->user_id }}" aria-haspopup="dialog" aria-controls="user-manage-dialog">
                                            Manage
                                        </button>

                                        <template id="manage-user-{{ $user->user_id }}">
                                            <div class="mb-6 flex items-start gap-4 border-b border-gray-200 pb-6">
                                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-blue-100 text-lg font-semibold text-blue-800" aria-hidden="true">{{ $initials }}</div>
                                                <div class="min-w-0 flex-1">
                                                    <h2 class="break-words text-xl font-semibold text-gray-900" data-drawer-title>{{ $user->full_name }}</h2>
                                                    <p class="break-all text-sm text-gray-600">{{ $user->email }}</p>
                                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        <span class="text-xs text-gray-500">Registered {{ $user->created_at?->format('M j, Y') ?? '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            @if ($canApprove)
                                                <form method="POST" action="{{ route('users.approve', $user) }}" class="mb-6 border-b border-gray-200 pb-6">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-primary-button>{{ __('Approve account') }}</x-primary-button>
                                                </form>
                                            @endif

                                            @if ($allowedRoles->isNotEmpty() || $canChangeDivision)
                                                <section class="border-b border-gray-200 pb-6" aria-labelledby="role-division-heading">
                                                    <h3 id="role-division-heading" class="text-sm font-semibold uppercase tracking-wide text-gray-900">Role and division</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Nothing changes until you save.</p>
                                                    <form class="user-role-division-form mt-4 space-y-4" data-role-url="{{ route('users.role', $user) }}" data-division-url="{{ route('users.division', $user) }}" data-user-id="{{ $user->user_id }}">
                                                        @csrf
                                                        @if ($allowedRoles->isNotEmpty())
                                                            <label class="block text-sm font-medium text-gray-700">
                                                                Role
                                                                <select name="role" data-original-value="{{ $user->role->value }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                    @foreach ($allowedRoles as $role)
                                                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->value }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>
                                                        @endif
                                                        @if ($canChangeDivision)
                                                            <label class="block text-sm font-medium text-gray-700">
                                                                Division
                                                                <select name="division" data-original-value="{{ $user->division->value }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                    @foreach (\App\Enums\Division::cases() as $division)
                                                                        <option value="{{ $division->value }}" @selected($user->division === $division)>{{ $division->value }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>
                                                        @endif
                                                        <x-primary-button type="submit">{{ __('Save changes') }}</x-primary-button>
                                                    </form>
                                                </section>
                                            @endif

                                            @if ($canResetPassword)
                                                <section class="border-b border-gray-200 py-6" aria-labelledby="security-heading">
                                                    <h3 id="security-heading" class="text-sm font-semibold uppercase tracking-wide text-gray-900">Security</h3>
                                                    <p class="mt-1 text-sm text-gray-600">Issue a one-time password that the employee must change at next sign in.</p>
                                                    <form method="POST" action="{{ route('users.password', $user) }}" class="reset-password-form mt-4">
                                                        @csrf
                                                        @method('PUT')
                                                        <x-secondary-button type="submit" data-user-name="{{ $user->full_name }}">Reset password</x-secondary-button>
                                                    </form>
                                                </section>
                                            @endif

                                            @if ($canSuspend || $canReactivate)
                                                <section class="pt-6" aria-labelledby="danger-zone-heading">
                                                    <h3 id="danger-zone-heading" class="text-sm font-semibold uppercase tracking-wide text-red-800">Danger zone</h3>
                                                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-4">
                                                        @if ($canSuspend)
                                                            <p class="text-sm text-gray-700">Suspending prevents this employee from signing in until their account is reactivated.</p>
                                                            <form method="POST" action="{{ route('users.suspend', $user) }}" class="danger-action-form mt-3" data-action-label="Suspend account" data-user-name="{{ $user->full_name }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-800">Suspend account</button>
                                                            </form>
                                                        @else
                                                            <p class="text-sm text-gray-700">Reactivating restores this employee’s ability to sign in.</p>
                                                            <form method="POST" action="{{ route('users.reactivate', $user) }}" class="danger-action-form mt-3" data-action-label="Reactivate account" data-user-name="{{ $user->full_name }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <x-secondary-button type="submit">Reactivate account</x-secondary-button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </section>
                                            @endif

                                            @can('viewSignature', $user)
                                                @if ($user->signature_path)
                                                    <div class="mt-6 border-t border-gray-200 pt-4">
                                                        <a class="text-sm underline text-blue-700" href="{{ route('users.signature', $user) }}" target="_blank" rel="noopener">View signature</a>
                                                    </div>
                                                @endif
                                            @endcan
                                        </template>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-600">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $users->links() }}</div>
        </div>
    </div>

    <dialog id="user-manage-dialog" class="fixed inset-0 m-0 h-full w-full max-w-none overflow-hidden bg-transparent p-0 text-left text-gray-900 backdrop:bg-transparent">
        <div id="user-manage-overlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-[250ms] ease-out motion-reduce:transition-none" aria-hidden="true"></div>
        <aside id="user-manage-panel" class="absolute inset-y-0 right-0 flex h-full w-full max-w-xl translate-x-full flex-col bg-white shadow-2xl transition-transform duration-[250ms] ease-out motion-reduce:transition-none" role="document" aria-label="Manage user">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 sm:px-7">
                <p class="text-sm font-semibold uppercase tracking-widest text-blue-800">Manage user</p>
                <button id="user-manage-close" type="button" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close manage panel">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div id="user-manage-content" class="min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-7"></div>
        </aside>
    </dialog>

    <dialog id="password-reset-dialog" class="w-full max-w-md rounded-lg p-6 shadow-xl backdrop:bg-gray-900/50">
        <h2 class="text-lg font-semibold text-gray-900">Temporary password</h2>
        <p class="mt-2 text-sm text-gray-600">Give this password to the employee. It will only be shown once, and they must change it when they sign in.</p>
        <code id="password-reset-value" class="mt-4 block break-all rounded bg-gray-100 p-3 text-lg text-gray-900"></code>
        <div class="mt-4 flex justify-end gap-2">
            <button id="password-reset-copy" type="button" class="rounded-md border border-blue-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-blue-800 hover:bg-blue-50">Copy</button>
            <button id="password-reset-close" type="button" class="rounded-md bg-blue-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-blue-800">Done</button>
        </div>
    </dialog>

</x-app-layout>
