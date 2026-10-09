<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserManagementAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'inactive'])],
            'division' => ['nullable', Rule::in(array_column(Division::cases(), 'value'))],
            'role' => ['nullable', Rule::in(array_column(Role::cases(), 'value'))],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                if ($status === 'inactive') {
                    $query->where('is_active', false);

                    return;
                }

                $query->where('status', $status)
                    ->where('is_active', true);
            })
            ->when($filters['division'] ?? null, fn ($query, string $division) => $query->where('division', $division))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('user_id')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function approve(User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($user, $audit): void {
            $user->refresh();
            $this->authorize('approve', $user);

            $oldValues = ['status' => $user->status->value, 'is_active' => $user->is_active];
            $user->forceFill(['status' => UserStatus::Active, 'is_active' => true])->save();

            $audit->record(auth()->user(), $user, 'approve', $oldValues, [
                'status' => UserStatus::Active->value,
                'is_active' => true,
            ]);
        });

        return back()->with('status', 'User approved.');
    }

    public function suspend(User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($user, $audit): void {
            User::query()
                ->where('role', Role::SuperAdministrator->value)
                ->where('status', UserStatus::Active->value)
                ->where('is_active', true)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get(['user_id']);

            $user->refresh();
            $this->authorize('suspend', $user);

            $oldValues = ['status' => $user->status->value, 'is_active' => $user->is_active];
            $user->forceFill(['status' => UserStatus::Suspended, 'is_active' => true])->save();

            $audit->record(auth()->user(), $user, 'suspend', $oldValues, [
                'status' => UserStatus::Suspended->value,
                'is_active' => true,
            ]);
        });

        return back()->with('status', 'User suspended.');
    }

    public function reactivate(User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($user, $audit): void {
            $user->refresh();
            $this->authorize('reactivate', $user);

            $oldValues = ['status' => $user->status->value, 'is_active' => $user->is_active];
            $user->forceFill(['status' => UserStatus::Active, 'is_active' => true])->save();

            $audit->record(auth()->user(), $user, 'reactivate', $oldValues, [
                'status' => UserStatus::Active->value,
                'is_active' => true,
            ]);
        });

        return back()->with('status', 'User reactivated.');
    }

    public function updateRole(Request $request, User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
        ]);
        DB::transaction(function () use ($user, $validated, $audit): void {
            User::query()
                ->where('role', Role::SuperAdministrator->value)
                ->where('status', UserStatus::Active->value)
                ->where('is_active', true)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get(['user_id']);

            $user->refresh();
            $this->authorize('changeRole', [$user, $validated['role']]);

            $oldRole = $user->role->value;
            $user->forceFill(['role' => Role::from($validated['role'])])->save();
            $audit->record(auth()->user(), $user, 'change_role', ['role' => $oldRole], [
                'role' => $validated['role'],
            ]);
        });

        return back()->with('status', 'User role updated.');
    }

    public function updateDivision(Request $request, User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'division' => ['required', Rule::in(array_column(Division::cases(), 'value'))],
        ]);

        DB::transaction(function () use ($user, $validated, $audit): void {
            $user->refresh();
            $this->authorize('changeDivision', $user);

            $oldDivision = $user->division->value;
            $user->forceFill(['division' => Division::from($validated['division'])])->save();
            $audit->record(auth()->user(), $user, 'change_division', ['division' => $oldDivision], [
                'division' => $validated['division'],
            ]);
        });

        return back()->with('status', 'User division updated.');
    }

    public function resetPassword(Request $request, User $user, UserManagementAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($user, $validated, $audit): void {
            $user->refresh();
            $this->authorize('resetPassword', $user);

            $user->forceFill(['password' => Hash::make($validated['password'])])->save();
            $audit->record(auth()->user(), $user, 'reset_password', ['password' => '[redacted]'], [
                'password' => '[temporary password set; value redacted]',
            ]);
        });

        return back()->with('status', 'Temporary password set.');
    }
}
