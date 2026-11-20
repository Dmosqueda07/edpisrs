<?php

namespace App\Livewire;

use App\Enums\ItSupportRequestStatus;
use App\Models\SupportRequest;
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
        Gate::authorize(Gate::allows('viewAssignedQueue', SupportRequest::class) ? 'viewAssignedQueue' : 'viewAssigned', SupportRequest::class);

        $this->validate([
            'status' => [Rule::when($this->status !== '', Rule::in(array_column(ItSupportRequestStatus::cases(), 'value')))],
        ]);

        $query = SupportRequest::query()
            ->with(['requester'])
            ->where('assigned_technician_id', auth()->id())
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status));

        $overdueQuery->joinLatestActivity($query);
        $query->select('support_requests.*')->selectRaw(
            'CASE WHEN support_requests.status IN (?, ?, ?) AND COALESCE(latest_request_activity.latest_activity, support_requests.updated_at) < ? THEN 1 ELSE 0 END AS is_overdue',
            [
                ItSupportRequestStatus::Assigned->value,
                ItSupportRequestStatus::InProgress->value,
                ItSupportRequestStatus::OnHold->value,
                $overdueQuery->cutoff()->toDateTimeString(),
            ],
        );

        $requests = $query
            ->orderByRaw("CASE support_requests.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByRaw('COALESCE(support_requests.requested_at, support_requests.created_at) ASC')
            ->paginate(10)
            ->withQueryString();

        return view('livewire.assigned-it-support-requests', compact('requests'));
    }
}
