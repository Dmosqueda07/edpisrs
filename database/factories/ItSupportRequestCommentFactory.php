<?php

namespace Database\Factories;

use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequestComment>
 */
class ItSupportRequestCommentFactory extends Factory
{
    protected $model = ItSupportRequestComment::class;

    public function definition(): array
    {
        $author = User::factory()->create();

        return [
            'it_support_request_id' => ItSupportRequest::factory(),
            'user_id' => $author->getKey(),
            'author_name' => $author->full_name,
            'body' => fake()->paragraph(),
            'proof_path' => null,
            'is_internal' => false,
            'read_at' => null,
        ];
    }
}
