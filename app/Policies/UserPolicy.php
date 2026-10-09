<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isAdministrator($actor);
    }

    public function approve(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target)
            && $target->status === UserStatus::Pending;
    }

    public function suspend(User $actor, User $target): bool
    {
        if (! $this->canManageTarget($actor, $target) || $actor->is($target)) {
            return false;
        }

        return ! $this->isLastActiveSuperAdministrator($target);
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target)
            && $target->status !== UserStatus::Pending
            && ($target->status !== UserStatus::Active || ! $target->is_active);
    }

    public function changeRole(User $actor, User $target, string $newRole): bool
    {
        $role = Role::tryFrom($newRole);

        if ($role === null || ! $this->canManageTarget($actor, $target) || $actor->is($target)) {
            return false;
        }

        if ($this->hasAdministratorRole($target->role) || $this->hasAdministratorRole($role)) {
            if ($actor->role !== Role::SuperAdministrator) {
                return false;
            }
        }

        return ! (
            $target->role === Role::SuperAdministrator
            && $role !== Role::SuperAdministrator
            && $this->isLastActiveSuperAdministrator($target)
        );
    }

    public function changeDivision(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $this->canManageTarget($actor, $target);
    }

    public function viewSignature(User $actor, User $target): bool
    {
        return $actor->is($target) || $this->isAdministrator($actor);
    }

    private function canManageTarget(User $actor, User $target): bool
    {
        if (! $this->isAdministrator($actor)) {
            return false;
        }

        return ! $this->hasAdministratorRole($target->role)
            || $actor->role === Role::SuperAdministrator;
    }

    private function isAdministrator(User $user): bool
    {
        return $user->status === UserStatus::Active
            && $user->is_active
            && in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true);
    }

    private function hasAdministratorRole(?Role $role): bool
    {
        return in_array($role, [Role::Administrator, Role::SuperAdministrator], true);
    }

    private function isLastActiveSuperAdministrator(User $target): bool
    {
        if (
            $target->role !== Role::SuperAdministrator
            || $target->status !== UserStatus::Active
            || ! $target->is_active
        ) {
            return false;
        }

        return User::query()
            ->where('role', Role::SuperAdministrator->value)
            ->where('status', UserStatus::Active->value)
            ->where('is_active', true)
            ->where('user_id', '<>', $target->getKey())
            ->lockForUpdate()
            ->get(['user_id'])
            ->isEmpty();
    }
}
