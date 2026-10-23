<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\ItSupportRequest;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NavigationBadgeCounts
{
    /** @return array{intake: int, approvals: int, assigned: int} */
    public function for(User $user): array
    {
        $counts = ['intake' => 0, 'approvals' => 0, 'assigned' => 0];
        $queries = [];

        if (auth()->user() && auth()->user()->can('manageAssignments', ItSupportRequest::class)) {
            $queries[] = DB::table('it_support_requests')
                ->selectRaw("'intake' AS badge, COUNT(*) AS total")
                ->whereNull('assigned_technician_id')
                ->where(function (Builder $query): void {
                    $query->where('status', ItSupportRequestStatus::Approved->value)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', ItSupportRequestStatus::Submitted->value)
                                ->where('requires_approval', false);
                        });
                });
        }

        if (auth()->user() && auth()->user()->can('viewApprovalQueue', ItSupportRequest::class)) {
            $queries[] = DB::table('it_support_requests')
                ->selectRaw("'approvals' AS badge, COUNT(*) AS total")
                ->where('status', ItSupportRequestStatus::PendingApproval->value)
                ->whereJsonContains('approval_roles', $user->role->value)
                ->when($user->role === Role::DivisionHead, fn (Builder $query) => $query->where('division', $user->division?->value));
        }

        if (auth()->user() && ($user->can('viewAssigned', ItSupportRequest::class) || $user->can('viewAssignedQueue', ItSupportRequest::class))) {
            $queries[] = DB::table('it_support_requests')
                ->selectRaw("'assigned' AS badge, COUNT(*) AS total")
                ->where('assigned_technician_id', $user->getKey())
                ->whereIn('status', OpenAssignedRequestCount::statusValues());
        }

        if ($queries !== []) {
            $query = array_shift($queries);
            foreach ($queries as $additional) {
                $query->unionAll($additional);
            }

            foreach ($query->get() as $row) {
                $counts[$row->badge] = (int) $row->total;
            }
        }

        return $counts;
    }
}
