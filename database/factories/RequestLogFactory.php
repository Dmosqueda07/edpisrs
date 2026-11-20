<?php

namespace Database\Factories;

use App\Models\SupportRequest;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestLog>
 */
class RequestLogFactory extends Factory
{
    protected $model = RequestLog::class;

    public function definition(): array
    {
        return [
            'requests_id' => SupportRequest::factory(),
            'user_id' => User::factory(),
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'submitted',
            'remarks' => null,
            'created_at' => now(),
        ];
    }
}
