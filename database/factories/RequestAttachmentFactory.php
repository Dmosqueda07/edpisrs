<?php

namespace Database\Factories;

use App\Models\SupportRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestAttachment>
 */
class RequestAttachmentFactory extends Factory
{
    protected $model = RequestAttachment::class;

    public function definition(): array
    {
        return [
            'requests_id' => SupportRequest::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'it-support-requests/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1, 5000000),
        ];
    }
}
