<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Support\WorkingDayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RequestOverdueQuery
{
    public function apply(Builder $query): Builder
    {
        return $this->whereOverdue($query);
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
                'COALESCE(support_requests.requested_at, support_requests.created_at) < ?',
                [$this->cutoff()->toDateTimeString()],
            );
    }

    public function cutoff(): CarbonImmutable
    {
        return (new WorkingDayCalculator)->subtractWorkingDays(2);
    }
}
