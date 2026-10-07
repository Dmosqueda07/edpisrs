<?php

namespace App\Livewire;

use App\Models\ItSupportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AssignedItSupportRequests extends Component
{
    use WithPagination;

    public function render(): View
    {
        Gate::authorize('viewAssigned', ItSupportRequest::class);

        return view('livewire.assigned-it-support-requests', [
            'requests' => ItSupportRequest::query()
                ->with('requester')
                ->where('assigned_technician_id', auth()->id())
                ->latest()
                ->paginate(10),
        ]);
    }
}
