<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

class RequestDashboardMetrics
{
    public function administratorCards(): array
    {
        $startOfToday = CarbonImmutable::today();
        $startOfTomorrow = $startOfToday->addDay();

        $groups = ItSupportRequest::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS status_total')
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND requested_at >= ? THEN 1 ELSE 0 END) AS new_requests',
                [ItSupportRequestStatus::Submitted->value, now()->subDays(7)->toDateTimeString()],
            )
            ->selectRaw(
                'SUM(CASE WHEN (status = ? OR (status = ? AND requires_approval = ?)) AND assigned_technician_id IS NULL THEN 1 ELSE 0 END) AS awaiting_assignment',
                [
                    ItSupportRequestStatus::Approved->value,
                    ItSupportRequestStatus::Submitted->value,
                    false,
                ],
            )
            ->selectRaw(
                'SUM(CASE WHEN resolved_at >= ? AND resolved_at < ? THEN 1 ELSE 0 END) AS resolved_today',
                [
                    $startOfToday->toDateTimeString(),
                    $startOfTomorrow->toDateTimeString(),
                ],
            )
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $countConditional = static fn (string $key): int => (int) $groups->sum($key);
        $countStatus = static fn (ItSupportRequestStatus $status): int => (int) ($groups->get($status->value)->status_total ?? 0);

        $overdue = app(RequestOverdueQuery::class)
            ->apply(ItSupportRequest::query())
            ->count('it_support_requests.id');

        return [
            $this->card('New requests (7 days)', $countConditional('new_requests'), [
                'status' => ItSupportRequestStatus::Submitted->value,
                'recent' => '7d',
            ]),
            $this->card('Pending approval', $countStatus(ItSupportRequestStatus::PendingApproval), [
                'status' => ItSupportRequestStatus::PendingApproval->value,
            ]),
            $this->card('Awaiting assignment', $countConditional('awaiting_assignment'), [
                'awaiting_assignment' => '1',
            ]),
            $this->card('In progress', $countStatus(ItSupportRequestStatus::InProgress), [
                'status' => ItSupportRequestStatus::InProgress->value,
            ]),
            $this->card('On hold', $countStatus(ItSupportRequestStatus::OnHold), [
                'status' => ItSupportRequestStatus::OnHold->value,
            ]),
            $this->card('Resolved today', $countConditional('resolved_today'), [
                'resolved_today' => '1',
            ]),
            $this->card('Overdue', $overdue, ['overdue' => '1']),
        ];
    }

    public function technicianCards(User $user): array
    {
        $groups = ItSupportRequest::query()
            ->where('assigned_technician_id', $user->getKey())
            ->select('status')
            ->selectRaw('COUNT(*) AS status_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $cards = [[
            'label' => 'My assigned requests',
            'count' => (int) $groups->sum('status_total'),
            'url' => route('edp.assigned-requests.index'),
        ]];

        return [...$cards, ...collect([
            ItSupportRequestStatus::Assigned,
            ItSupportRequestStatus::InProgress,
            ItSupportRequestStatus::OnHold,
            ItSupportRequestStatus::Resolved,
        ])->map(fn (ItSupportRequestStatus $status): array => [
            'label' => ucfirst(str_replace('_', ' ', $status->value)),
            'count' => (int) ($groups->get($status->value)->status_total ?? 0),
            'url' => route('edp.assigned-requests.index', ['status' => $status->value]),
        ])->all()];
    }

    public function employeeCards(User $user): array
    {
        $groups = ItSupportRequest::query()
            ->where('requester_user_id', $user->getKey())
            ->select('status')
            ->selectRaw('COUNT(*) AS status_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(ItSupportRequestStatus::cases())
            ->map(fn (ItSupportRequestStatus $status): array => [
                'label' => ucfirst(str_replace('_', ' ', $status->value)),
                'count' => (int) ($groups->get($status->value)->status_total ?? 0),
                'url' => route('it-support-requests.index', ['status' => $status->value]),
            ])->all();
    }

    private function card(string $label, int $count, array $filters): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'url' => route('edp.it-support-requests.index', $filters),
        ];
    }
}
