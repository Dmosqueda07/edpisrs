<?php

namespace App\Livewire;

use App\Enums\Division;
use App\Enums\ItSupportRequestPriority;
use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RequestOverdueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ManageItSupportRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'tab', keep: true)]
    public string $status = 'submitted';

    #[Url(as: 'request_type')]
    public string $requestType = '';

    #[Url]
    public string $division = '';

    #[Url]
    public string $priority = '';

    #[Url(as: 'assigned_technician')]
    public string $technician = '';

    #[Url(as: 'requested_from')]
    public string $requestedFrom = '';

    #[Url(as: 'requested_to')]
    public string $requestedTo = '';

    #[Url]
    public string $recent = '';

    #[Url(as: 'awaiting_assignment')]
    public bool $awaitingAssignment = false;

    #[Url(as: 'resolved_today')]
    public bool $resolvedToday = false;

    #[Url]
    public bool $overdue = false;

    #[Url]
    public string $sort = 'oldest';

    public ?int $assignmentRequestId = null;

    public string $assignmentTechnician = '';

    public string $assignmentPriority = '';

    public bool $filtersOpen = false;

    public function updated(string $property): void
    {
        if ($property === 'status') {
            $this->closeAssignment();

            if (! in_array($this->status, ['', 'submitted', 'approved', 'assigned', 'in_progress', 'on_hold'], true)) {
                $this->overdue = false;
            }
        }

        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'requestType',
            'division',
            'priority',
            'technician',
            'requestedFrom',
            'requestedTo',
            'recent',
            'awaitingAssignment',
            'resolvedToday',
            'overdue',
        ]);
        $this->sort = 'oldest';
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    public function openAssignment(int $requestId): void
    {
        $supportRequest = ItSupportRequest::query()->findOrFail($requestId);
        Gate::authorize('assign', $supportRequest);

        $this->assignmentRequestId = $requestId;
        $this->assignmentTechnician = (string) ($supportRequest->assigned_technician_id ?? '');
        $this->assignmentPriority = $supportRequest->priority->value;
    }

    public function closeAssignment(): void
    {
        $this->assignmentRequestId = null;
        $this->assignmentTechnician = '';
        $this->assignmentPriority = '';
    }

    public function render(): View
    {
        Gate::authorize('manageAssignments', ItSupportRequest::class);

        $validated = $this->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [Rule::when($this->status !== '', Rule::in(array_column(ItSupportRequestStatus::cases(), 'value')))],
            'requestType' => [Rule::when($this->requestType !== '', ['integer', Rule::exists('request_types', 'id')])],
            'division' => [Rule::when($this->division !== '', Rule::in(array_column(Division::cases(), 'value')))],
            'priority' => [Rule::when($this->priority !== '', Rule::in(array_column(ItSupportRequestPriority::cases(), 'value')))],
            'technician' => [
                Rule::when(
                    $this->technician !== '' && $this->technician !== 'unassigned',
                    ['integer', Rule::exists('users', 'user_id')],
                ),
                Rule::when($this->technician === 'unassigned', Rule::in(['unassigned'])),
            ],
            'requestedFrom' => [Rule::when($this->requestedFrom !== '', ['date_format:Y-m-d'])],
            'requestedTo' => [
                Rule::when(
                    $this->requestedTo !== '',
                    $this->requestedFrom !== ''
                        ? ['date_format:Y-m-d', 'after_or_equal:requestedFrom']
                        : ['date_format:Y-m-d'],
                ),
            ],
            'recent' => [Rule::when($this->recent !== '', Rule::in(['7d']))],
            'sort' => ['required', Rule::in(['age', 'oldest', 'newest', 'priority'])],
            'awaitingAssignment' => ['boolean'],
            'resolvedToday' => ['boolean'],
            'overdue' => ['boolean'],
        ]);

        $query = ItSupportRequest::query()
            ->select('it_support_requests.*')
            ->selectRaw('COALESCE(latest_request_activity.latest_activity, it_support_requests.updated_at) AS last_activity_at');
        app(RequestOverdueQuery::class)->joinLatestActivity($query);
        $this->applyFilters($query, $validated);

        $statusCounts = (clone $query)
            ->reorder()
            ->select('it_support_requests.status')
            ->selectRaw('COUNT(*) AS request_count')
            ->groupBy('it_support_requests.status')
            ->pluck('request_count', 'status')
            ->map(fn ($count): int => (int) $count);

        $query->with(['technician', 'requestType'])
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('it_support_requests.status', $status));

        if (($validated['sort'] ?? 'oldest') === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END");
        }

        $query
            ->when(($validated['sort'] ?? 'oldest') === 'newest', fn ($query) => $query->orderByDesc('requested_at')->orderByDesc('id'))
            ->when(in_array(($validated['sort'] ?? 'oldest'), ['oldest', 'age', 'priority'], true), fn ($query) => $query->orderBy('requested_at')->orderBy('id'));

        $requests = $query->paginate(20)
            ->appends(['tab' => $validated['status'] ?? ''])
            ->withQueryString();

        $workloads = DB::table('it_support_requests')
            ->select('assigned_technician_id')
            ->selectRaw('COUNT(*) AS open_workload')
            ->whereIn('status', [
                ItSupportRequestStatus::Assigned->value,
                ItSupportRequestStatus::InProgress->value,
                ItSupportRequestStatus::OnHold->value,
            ])
            ->whereNotNull('assigned_technician_id')
            ->groupBy('assigned_technician_id')
            ->pluck('open_workload', 'assigned_technician_id');

        $technicians = User::technicians()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $technicians->each(fn (User $technician) => $technician->setAttribute(
            'open_workload',
            (int) ($workloads->get($technician->getKey()) ?? 0),
        ));

        return view('livewire.manage-it-support-requests', [
            'requests' => $requests,
            'technicians' => $technicians,
            'requestTypes' => RequestType::query()->orderBy('name')->get(),
            'divisions' => Division::cases(),
            'priorities' => ItSupportRequestPriority::cases(),
            'statuses' => [
                ItSupportRequestStatus::Submitted,
                ItSupportRequestStatus::PendingApproval,
                ItSupportRequestStatus::Approved,
                ItSupportRequestStatus::Assigned,
                ItSupportRequestStatus::InProgress,
                ItSupportRequestStatus::OnHold,
                ItSupportRequestStatus::Resolved,
                ItSupportRequestStatus::Closed,
                ItSupportRequestStatus::Cancelled,
                ItSupportRequestStatus::Rejected,
            ],
            'statusCounts' => $statusCounts,
            'allCount' => $statusCounts->sum(),
            'activeFilterCount' => collect([
                $validated['requestType'] ?? '',
                $validated['division'] ?? '',
                $validated['priority'] ?? '',
                $validated['technician'] ?? '',
                $validated['requestedFrom'] ?? '',
                $validated['requestedTo'] ?? '',
                $validated['recent'] ?? '',
                ($validated['awaitingAssignment'] ?? false) ? 'awaiting_assignment' : '',
                ($validated['resolvedToday'] ?? false) ? 'resolved_today' : '',
                ($validated['overdue'] ?? false) ? 'overdue' : '',
            ])->filter(fn ($value) => $value !== '')->count(),
            'overdueCutoff' => app(RequestOverdueQuery::class)->cutoff(),
            'assignmentRequest' => $this->assignmentRequestId
                ? ItSupportRequest::query()->with(['technician'])->find($this->assignmentRequestId)
                : null,
            'assignmentTechnician' => $this->assignmentTechnician,
            'assignmentPriority' => $this->assignmentPriority,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyFilters(Builder $query, array $validated): void
    {
        $query
            ->when(trim((string) ($validated['search'] ?? '')) !== '', function (Builder $query) use ($validated): void {
                $search = trim((string) $validated['search']);
                $searchTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                $query->where(function (Builder $query) use ($search, $searchTerms): void {
                    $query->where('ticket_no', 'like', $search.'%')
                        ->orWhere('requester_name', 'like', '%'.$search.'%');

                    if ($searchTerms !== []) {
                        $query->orWhereHas('requester', function (Builder $requester) use ($searchTerms): void {
                            foreach ($searchTerms as $term) {
                                $requester->where(function (Builder $requester) use ($term): void {
                                    $requester->where('first_name', 'like', '%'.$term.'%')
                                        ->orWhere('last_name', 'like', '%'.$term.'%');
                                });
                            }
                        });
                    }
                });
            })
            ->when($validated['requestType'] ?? null, fn (Builder $query, string $type) => $query->where('request_type_id', $type))
            ->when($validated['division'] ?? null, fn (Builder $query, string $division) => $query->where('division', $division))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($validated['technician'] ?? null, function (Builder $query, string $technician): void {
                $technician === 'unassigned'
                    ? $query->whereNull('assigned_technician_id')
                    : $query->where('assigned_technician_id', $technician);
            })
            ->when($validated['requestedFrom'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($validated['requestedTo'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '<=', $date))
            ->when(($validated['recent'] ?? null) === '7d', fn (Builder $query) => $query->where('requested_at', '>=', now()->subDays(7)))
            ->when($validated['awaitingAssignment'] ?? false, function (Builder $query): void {
                $query->whereNull('assigned_technician_id')
                    ->where(function (Builder $query): void {
                        $query->where('status', ItSupportRequestStatus::Approved->value)
                            ->orWhere(function (Builder $query): void {
                                $query->where('status', ItSupportRequestStatus::Submitted->value)
                                    ->where('requires_approval', false);
                            });
                    });
            })
            ->when($validated['resolvedToday'] ?? false, fn (Builder $query) => $query
                ->whereDate('resolved_at', today()))
            ->when($validated['overdue'] ?? false, fn (Builder $query) => app(RequestOverdueQuery::class)->whereOverdue($query));
    }
}
