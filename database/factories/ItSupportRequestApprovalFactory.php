<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequestApproval>
 */
class ItSupportRequestApprovalFactory extends Factory
{
    protected $model = ItSupportRequestApproval::class;

    public function definition(): array
    {
        $approver = User::factory()->create();

        return [
            'it_support_request_id' => ItSupportRequest::factory(),
            'approver_user_id' => $approver->getKey(),
            'approver_name' => $approver->full_name,
            'approver_role' => $approver->role->value,
            'decision' => ApprovalDecision::Approved,
            'comment' => null,
            'decided_at' => now(),
        ];
    }
}
