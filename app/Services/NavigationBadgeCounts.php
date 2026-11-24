<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NavigationBadgeCounts
{
    /** @return array{intake: int, assigned: int} */
    public function for(User $user): array
    {
        $counts = ['intake' => 0, 'assigned' => 0];
        $queries = [];

        if ($user->can('viewIntake', SupportRequest::class)) {
            $queries[] = DB::table('support_requests')
                ->selectRaw("'intake' AS badge, COUNT(*) AS total")
                ->whereNull('assigned_technician_id')
                ->where('status', ItSupportRequestStatus::Submitted->value);
        }

        if ($user->can('viewAssigned', SupportRequest::class) || $user->can('viewAssignedQueue', SupportRequest::class)) {
            $queries[] = DB::table('support_requests')
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
