<?php

namespace App\Livewire;

use App\Models\ItSupportRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ManageItSupportRequests extends Component
{
    use WithPagination;

    public function render(): View
    {
        Gate::authorize('manageAssignments', ItSupportRequest::class);

        return view('livewire.manage-it-support-requests', [
            'requests' => ItSupportRequest::query()
                ->with('technician')
                ->latest()
                ->paginate(10),
            'technicians' => User::technicians()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }
}
