<?php

namespace Database\Factories;

use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequestLog>
 */
class ItSupportRequestLogFactory extends Factory
{
    protected $model = ItSupportRequestLog::class;

    public function definition(): array
    {
        return [
            'it_support_request_id' => ItSupportRequest::factory(),
            'user_id' => User::factory(),
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'submitted',
            'remarks' => null,
            'created_at' => now(),
        ];
    }
}
