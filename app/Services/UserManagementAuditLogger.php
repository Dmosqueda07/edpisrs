<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserManagementAuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(User $actor, User $target, string $action, array $oldValues, array $newValues): void
    {
        Log::channel('audit')->info('User management action', [
            'actor_user_id' => $actor->getKey(),
            'target_user_id' => $target->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
