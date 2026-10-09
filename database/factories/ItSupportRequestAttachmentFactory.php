<?php

namespace Database\Factories;

use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItSupportRequestAttachment>
 */
class ItSupportRequestAttachmentFactory extends Factory
{
    protected $model = ItSupportRequestAttachment::class;

    public function definition(): array
    {
        return [
            'it_support_request_id' => ItSupportRequest::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'it-support-requests/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1, 5000000),
        ];
    }
}
