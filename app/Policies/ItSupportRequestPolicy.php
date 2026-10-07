<?php

namespace App\Policies;

use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ItSupportRequest;
use App\Models\User;

class ItSupportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true)
            || $itSupportRequest->requester_user_id === $user->user_id
            || $itSupportRequest->assigned_technician_id === $user->user_id
            || $this->isEligibleApprover($user, $itSupportRequest);
    }

    public function viewApprovalQueue(User $user): bool
    {
        return $this->isActive($user)
            && in_array($user->role, [
                Role::Administrator,
                Role::SuperAdministrator,
                Role::DivisionHead,
                Role::RecordsChief,
                Role::CityAssessor,
            ], true);
    }

    public function approve(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->status === ItSupportRequestStatus::PendingApproval
            && $this->isEligibleApprover($user, $itSupportRequest);
    }

    public function manageAssignments(User $user): bool
    {
        return in_array($user->role, [Role::Administrator, Role::SuperAdministrator], true);
    }

    public function viewAssigned(User $user): bool
    {
        return User::technicians()->whereKey($user->getKey())->exists();
    }

    public function assign(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $this->manageAssignments($user)
            && in_array($itSupportRequest->status, [
                ItSupportRequestStatus::Submitted,
                ItSupportRequestStatus::Assigned,
            ], true);
    }

    public function startWork(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::Assigned;
    }

    public function resolve(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $this->isAssignedTechnician($user, $itSupportRequest)
            && $itSupportRequest->status === ItSupportRequestStatus::InProgress;
    }

    public function confirmCompletion(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->requester_user_id === $user->user_id
            && $itSupportRequest->status === ItSupportRequestStatus::Resolved;
    }

    public function comment(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->status !== ItSupportRequestStatus::Completed
            && (
                $itSupportRequest->requester_user_id === $user->user_id
                || $this->isAssignedTechnician($user, $itSupportRequest)
            );
    }

    public function addProof(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->status !== ItSupportRequestStatus::Completed
            && $this->isAssignedTechnician($user, $itSupportRequest);
    }

    private function isAssignedTechnician(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $itSupportRequest->assigned_technician_id === $user->user_id
            && User::technicians()->whereKey($user->getKey())->exists();
    }

    private function isActive(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->is_active;
    }

    private function isEligibleApprover(User $user, ItSupportRequest $itSupportRequest): bool
    {
        return $this->isActive($user)
            && $itSupportRequest->requires_approval
            && in_array($user->role?->value, $itSupportRequest->approval_roles ?? [], true)
            && (
                $user->role !== Role::DivisionHead
                || $user->division?->value === $itSupportRequest->division
            );
    }
}
