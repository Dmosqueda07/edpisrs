<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Enums\UserStatus;
use App\Models\RequestLog;
use App\Models\SupportRequest;
use App\Models\User;

class RequestDashboardMetrics
{
    public function administratorDashboard(User $user): array
    {
        $groups = SupportRequest::query();
        $groups = $groups
            ->select('support_requests.status')
            ->selectRaw('COUNT(*) AS status_total')
            ->selectRaw('SUM(CASE WHEN support_requests.status = ? AND support_requests.requested_at >= ? THEN 1 ELSE 0 END) AS new_requests', [ItSupportRequestStatus::Submitted->value, now()->subDays(7)->toDateTimeString()])
            ->selectRaw('SUM(CASE WHEN support_requests.status = ? AND support_requests.assigned_technician_id IS NULL THEN 1 ELSE 0 END) AS available_to_claim', [ItSupportRequestStatus::Submitted->value])
            ->selectRaw('SUM(CASE WHEN support_requests.resolved_at >= ? AND support_requests.resolved_at < ? THEN 1 ELSE 0 END) AS resolved_today', [today()->toDateTimeString(), today()->addDay()->toDateTimeString()])
            ->selectRaw('SUM(CASE WHEN support_requests.assigned_technician_id = ? AND support_requests.status IN (?, ?, ?) THEN 1 ELSE 0 END) AS assigned_to_me', [$user->getKey(), ...OpenAssignedRequestCount::statusValues()])
            ->selectRaw('SUM(CASE WHEN support_requests.status IN (?, ?, ?, ?) AND COALESCE(support_requests.requested_at, support_requests.created_at) < ? THEN 1 ELSE 0 END) AS overdue', [ItSupportRequestStatus::Submitted->value, ItSupportRequestStatus::Assigned->value, ItSupportRequestStatus::InProgress->value, ItSupportRequestStatus::OnHold->value, app(RequestOverdueQuery::class)->cutoff()->toDateTimeString()])
            ->groupBy('support_requests.status')->get()->keyBy('status');

        $sum = static fn (string $key): int => (int) $groups->sum($key);
        $byStatus = static fn (ItSupportRequestStatus $status): int => (int) ($groups->get($status->value)->status_total ?? 0);
        $overview = [
            $this->card('New requests (7 days)', $sum('new_requests'), ['tab' => ItSupportRequestStatus::Submitted->value, 'recent' => '7d']),
            $this->card('Available to claim', $sum('available_to_claim'), ['awaiting_claim' => '1']),
            $this->card('In progress', $byStatus(ItSupportRequestStatus::InProgress), ['tab' => ItSupportRequestStatus::InProgress->value]),
            $this->card('On hold', $byStatus(ItSupportRequestStatus::OnHold), ['tab' => ItSupportRequestStatus::OnHold->value]),
            $this->card('Resolved today', $sum('resolved_today'), ['resolved_today' => '1']),
            $this->card('Overdue', $sum('overdue'), ['overdue' => '1']),
        ];

        $userGroups = User::query()->select('status')->selectRaw('COUNT(*) AS status_total')->groupBy('status')->get()->keyBy('status');
        $userCount = (int) $userGroups->sum('status_total');
        $pendingUsers = (int) ($userGroups->get(UserStatus::Pending->value)->status_total ?? 0);
        $suspendedUsers = (int) ($userGroups->get(UserStatus::Suspended->value)->status_total ?? 0);

        $monthStart = now()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();
        $monthly = SupportRequest::query()->selectRaw('SUM(CASE WHEN requested_at >= ? AND requested_at < ? THEN 1 ELSE 0 END) AS received_count', [$monthStart->toDateTimeString(), $nextMonth->toDateTimeString()])
            ->selectRaw('SUM(CASE WHEN resolved_at >= ? AND resolved_at < ? THEN 1 ELSE 0 END) AS resolved_count', [$monthStart->toDateTimeString(), $nextMonth->toDateTimeString()])->first();

        $recentActivity = RequestLog::query()->with([
            'user:user_id,first_name,last_name,role',
            'supportRequest:requests_id,ticket_no',
        ])->orderByDesc('created_at')->orderByDesc('logs_id')->limit(5)->get();

        return [
            'overview' => $overview,
            'assignedCount' => $sum('assigned_to_me'),
            'shortcuts' => [
                'users' => ['total' => $userCount, 'pending' => $pendingUsers, 'suspended' => $suspendedUsers, 'url' => route('users.index')],
                'intake' => ['awaiting' => $sum('available_to_claim'), 'overdue' => $sum('overdue'), 'url' => route('edp.it-support-requests.index')],
                'reports' => ['received' => (int) ($monthly->received_count ?? 0), 'resolved' => (int) ($monthly->resolved_count ?? 0), 'url' => route('reports.index')],
                'activity' => ['entries' => $recentActivity, 'url' => route('activity-logs.index')],
            ],
        ];
    }

    public function administratorCards(): array
    {
        return $this->administratorDashboard(request()->user())['overview'];
    }

    public function technicianCards(User $user): array
    {
        $groups = SupportRequest::query()->where('assigned_technician_id', $user->getKey())->select('status')->selectRaw('COUNT(*) AS status_total')->groupBy('status')->get()->keyBy('status');
        $cards = [['label' => 'My assigned requests', 'count' => (int) $groups->sum('status_total'), 'url' => route('edp.assigned-requests.index')]];

        return [...$cards, ...collect([ItSupportRequestStatus::Assigned, ItSupportRequestStatus::InProgress, ItSupportRequestStatus::OnHold, ItSupportRequestStatus::Resolved])->map(fn (ItSupportRequestStatus $status): array => [
            'label' => ucfirst(str_replace('_', ' ', $status->value)), 'count' => (int) ($groups->get($status->value)->status_total ?? 0), 'url' => route('edp.assigned-requests.index', ['status' => $status->value]),
        ])->all()];
    }

    public function employeeCards(User $user): array
    {
        $groups = SupportRequest::query()->where('requester_user_id', $user->getKey())->select('status')->selectRaw('COUNT(*) AS status_total')->groupBy('status')->get()->keyBy('status');

        return collect(ItSupportRequestStatus::cases())->map(fn (ItSupportRequestStatus $status): array => [
            'label' => ucfirst(str_replace('_', ' ', $status->value)),
            'count' => (int) ($groups->get($status->value)->status_total ?? 0),
            'url' => route('it-support-requests.index', ['status' => $status->value]),
        ])->all();
    }

    private function card(string $label, int $count, array $filters): array
    {
        return ['label' => $label, 'count' => $count, 'url' => route('edp.it-support-requests.index', $filters)];
    }
}
