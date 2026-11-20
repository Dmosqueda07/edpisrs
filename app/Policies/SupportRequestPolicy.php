<?php

namespace App\Policies;

use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\SupportRequest;
use App\Models\User;

class SupportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportRequest $itSupportRequest): bool
    {
        return in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true)
            || $this->sameUserId($itSupportRequest->requester_user_id, $user)
            || $this->sameUserId($itSupportRequest->assigned_technician_id, $user);
    }

    public function manageAssignments(User $user): bool
    {
        return $this->isActive($user)
            && in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true);
    }

    public function viewAdminDashboard(User $user): bool
    {
        return in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true);
    }

    public function viewAssignedQueue(User $user): bool
    {
        return $this->viewAdminDashboard($user);
    }

    public function viewAssigned(User $user): bool
    {
        return User::technicians()->whereKey($user->getKey())->exists();
    }

    public function assign(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->manageAssignments($user)
            && in_array($itSupportRequest->status, [
                ItSupportRequestStatus::Submitted,
                ItSupportRequestStatus::Assigned,
                ItSupportRequestStatus::InProgress,
                ItSupportRequestStatus::OnHold,
            ], true);
    }

    public function startWork(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::Assigned;
    }

    public function resolve(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::InProgress;
    }

    public function hold(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::InProgress;
    }

    public function resume(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::OnHold;
    }

    public function confirmCompletion(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->sameUserId($itSupportRequest->requester_user_id, $user)
            && $itSupportRequest->status === ItSupportRequestStatus::Resolved;
    }

    public function cancel(User $user, SupportRequest $itSupportRequest): bool
    {
        if (in_array($itSupportRequest->status, [
            ItSupportRequestStatus::Resolved,
            ItSupportRequestStatus::Closed,
            ItSupportRequestStatus::Cancelled,
        ], true)) {
            return false;
        }

        if (in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true)) {
            return true;
        }

        return $this->sameUserId($itSupportRequest->requester_user_id, $user)
            && in_array($itSupportRequest->status, [
                ItSupportRequestStatus::Submitted,
            ], true);
    }

    public function reopen(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->sameUserId($itSupportRequest->requester_user_id, $user)
            && $itSupportRequest->status === ItSupportRequestStatus::Resolved
            && $itSupportRequest->resolved_at !== null
            && $itSupportRequest->resolved_at->greaterThanOrEqualTo(now()->subDays(3))
            && $itSupportRequest->assigned_technician_id !== null;
    }

    public function addProof(User $user, SupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->status !== ItSupportRequestStatus::Closed
            && $this->isAssignedTechnician($user, $itSupportRequest);
    }

    private function isAssignedTechnician(User $user, SupportRequest $itSupportRequest): bool
    {
        return $this->sameUserId($itSupportRequest->assigned_technician_id, $user)
            && User::technicians()->whereKey($user->getKey())->exists();
    }

    private function sameUserId(int|string|null $userId, User $user): bool
    {
        return $userId !== null && (string) $userId === (string) $user->getKey();
    }

    private function isActive(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->is_active;
    }
}
