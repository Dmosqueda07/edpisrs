<?php

namespace Database\Factories;

use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequestDetail>
 */
class ItSupportRequestDetailFactory extends Factory
{
    protected $model = ItSupportRequestDetail::class;

    public function definition(): array
    {
        return [
            'it_support_request_id' => ItSupportRequest::factory(),
            'field_key' => fake()->unique()->slug(2),
            'field_label' => fake()->words(3, true),
            'field_value' => fake()->sentence(),
        ];
    }
}
