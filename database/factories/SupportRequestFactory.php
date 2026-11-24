<?php

namespace Database\Factories;

use App\Enums\ItSupportRequestStatus;
use App\Models\RequestType;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    protected $model = SupportRequest::class;

    public function definition(): array
    {
        return [
            'requester_user_id' => User::factory(),
            'request_types_id' => RequestType::factory(),
            'summary' => fake()->sentence(5),
            'requester_name' => fake()->name(),
            'division' => 'CA',
            'support_type' => 'Other IT Request',
            'follow_up_question' => 'Please Specify',
            'answers' => ['details' => fake()->sentence()],
            'certified_at' => now(),
            'requested_at' => now(),
            'status' => ItSupportRequestStatus::Submitted,
        ];
    }
}
