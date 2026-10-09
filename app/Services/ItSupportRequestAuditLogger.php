<?php

namespace App\Services;

use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\User;

class ItSupportRequestAuditLogger
{
    public function record(
        ItSupportRequest $request,
        ?User $actor,
        string $action,
        ?ItSupportRequestStatus $fromStatus = null,
        ?ItSupportRequestStatus $toStatus = null,
        ?string $remarks = null,
    ): void {
        $request->logs()->create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'remarks' => $remarks,
        ]);
    }
}
