<?php

namespace App\Http\Requests;

use App\Enums\Division;
use App\Enums\UserStatus;
use App\Models\ItSupportRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignItSupportRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supportRequest = $this->route('it_support_request');

        return $supportRequest instanceof ItSupportRequest
            && $this->user()?->can('assign', $supportRequest) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'technician_id' => [
                'required',
                'integer',
                Rule::exists('users', 'user_id')
                    ->where('division', Division::EDP->value)
                    ->where('status', UserStatus::Active->value)
                    ->where('is_active', true),
            ],
        ];
    }
}
