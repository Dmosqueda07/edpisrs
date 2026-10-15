<?php

namespace App\Livewire;

use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Services\RequestOverdueQuery;
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

    public function render(RequestOverdueQuery $overdueQuery): View
    {
        Gate::authorize('viewAssignedQueue', ItSupportRequest::class);

        $this->validate([
            'status' => [Rule::when($this->status !== '', Rule::in(array_column(ItSupportRequestStatus::cases(), 'value')))],
        ]);

        $query = ItSupportRequest::query()
            ->with(['requester', 'detailsRows'])
                ->where('assigned_technician_id', auth()->id())
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status));

        $overdueQuery->joinLatestActivity($query);
        $query->select('it_support_requests.*')->selectRaw(
            "CASE WHEN it_support_requests.status IN (?, ?, ?) AND COALESCE(latest_request_activity.latest_activity, it_support_requests.updated_at) < ? THEN 1 ELSE 0 END AS is_overdue",
            [
                ItSupportRequestStatus::Assigned->value,
                ItSupportRequestStatus::InProgress->value,
                ItSupportRequestStatus::OnHold->value,
                $overdueQuery->cutoff()->toDateTimeString(),
            ],
        );

        $requests = $query
            ->orderByRaw("CASE it_support_requests.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByRaw('COALESCE(it_support_requests.requested_at, it_support_requests.created_at) ASC')
            ->paginate(10)
            ->withQueryString();

        return view('livewire.assigned-it-support-requests', compact('requests'));
    }
}
