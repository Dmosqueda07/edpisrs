<?php

namespace Database\Factories;

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequest>
 */
class ItSupportRequestFactory extends Factory
{
    protected $model = ItSupportRequest::class;

    public function definition(): array
    {
        return [
            'requester_user_id' => User::factory(),
            'request_type_id' => RequestType::factory(),
            'summary' => fake()->sentence(5),
            'priority' => 'normal',
            'requester_name' => fake()->name(),
            'division' => Division::CA->value,
            'support_type' => 'Other IT Request',
            'follow_up_question' => 'Please Specify',
            'details' => fake()->sentence(),
            'certified_at' => now(),
            'requested_at' => now(),
            'status' => ItSupportRequestStatus::Submitted,
        ];
    }
}
