<?php

namespace App\Http\Requests;

use App\Models\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItSupportRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'request_type_id' => [
                'required',
                'integer',
                Rule::exists('request_types', 'id')->where('is_active', true),
            ],
            'details' => [
                Rule::requiredIf(fn () => RequestType::active()->whereKey($this->input('request_type_id'))->exists()),
                'string',
            ],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
            'certification' => ['accepted'],
        ];
    }
}
