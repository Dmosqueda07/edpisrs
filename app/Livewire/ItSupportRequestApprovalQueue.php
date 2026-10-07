<?php

namespace App\Livewire;

use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\ItSupportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ItSupportRequestApprovalQueue extends Component
{
    use WithPagination;

    public function render(): View
    {
        $user = auth()->user();
        Gate::authorize('viewApprovalQueue', ItSupportRequest::class);

        $requests = ItSupportRequest::query()
            ->with('requester')
            ->where('status', ItSupportRequestStatus::PendingApproval->value)
            ->whereJsonContains('approval_roles', $user->role->value)
            ->when(
                $user->role === Role::DivisionHead,
                fn ($query) => $query->where('division', $user->division->value),
            )
            ->latest()
            ->paginate(10);

        return view('livewire.it-support-request-approval-queue', [
            'requests' => $requests,
        ]);
    }
}
