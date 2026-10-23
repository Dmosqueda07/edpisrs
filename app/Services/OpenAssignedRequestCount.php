<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;

class OpenAssignedRequestCount
{
    /** @return list<ItSupportRequestStatus> Open statuses used by the dashboard and navbar. */
    public static function statuses(): array
    {
        return [
            ItSupportRequestStatus::Assigned,
            ItSupportRequestStatus::InProgress,
            ItSupportRequestStatus::OnHold,
        ];
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return array_map(static fn (ItSupportRequestStatus $status): string => $status->value, self::statuses());
    }
}
