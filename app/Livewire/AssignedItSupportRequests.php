<?php

namespace App\Livewire;

use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AssignedItSupportRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAssigned', ItSupportRequest::class);

        $this->validate([
            'status' => [Rule::when($this->status !== '', Rule::in(array_column(ItSupportRequestStatus::cases(), 'value')))],
        ]);

        return view('livewire.assigned-it-support-requests', [
            'requests' => ItSupportRequest::query()
                ->with('requester')
                ->where('assigned_technician_id', auth()->id())
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }
}
