<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Support\WorkingDayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RequestOverdueQuery
{
    public function apply(Builder $query): Builder
    {
        $this->joinLatestActivity($query);

        return $this->whereOverdue($query);
    }

    public function joinLatestActivity(Builder $query): Builder
    {
        $latestActivity = DB::table('request_logs')
            ->select('requests_id')
            ->selectRaw('MAX(created_at) AS latest_activity')
            ->groupBy('requests_id');

        return $query->leftJoinSub($latestActivity, 'latest_request_activity', function ($join): void {
            $join->on('latest_request_activity.requests_id', '=', 'support_requests.requests_id');
        });
    }

    public function whereOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('support_requests.status', [
                ItSupportRequestStatus::Submitted->value,
                ItSupportRequestStatus::Assigned->value,
                ItSupportRequestStatus::InProgress->value,
                ItSupportRequestStatus::OnHold->value,
            ])
            ->whereRaw(
                'COALESCE(latest_request_activity.latest_activity, support_requests.updated_at) < ?',
                [$this->cutoff()->toDateTimeString()],
            );
    }

    public function cutoff(): CarbonImmutable
    {
        return (new WorkingDayCalculator)->subtractWorkingDays(2);
    }
}
