<?php

namespace Database\Factories;

use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestType>
 */
class RequestTypeFactory extends Factory
{
    protected $model = RequestType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'key' => Str::slug($name),
            'name' => $name,
            'category' => null,
            'remote_allowed' => false,
            'follow_up_question' => 'Additional details',
            'requires_approval' => false,
            'approval_roles' => [],
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
