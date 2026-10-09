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

    protected function prepareForValidation(): void
    {
        if ($this->has('answers') || ! $this->filled('details')) {
            return;
        }

        $requestType = RequestType::query()->find($this->input('request_type_id'));
        $definition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $requestType?->key);

        if ($definition) {
            $this->merge([
                'answers' => [
                    $definition['fields'][0]['key'] => $this->input('details'),
                ],
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $requestType = RequestType::query()->find($this->input('request_type_id'));
        $definition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $requestType?->key);
        $rules = [
            'request_type_id' => [
                'required',
                'integer',
                Rule::exists('request_types', 'id')->where('is_active', true),
            ],
            'answers' => ['required', 'array'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:5120',
                'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp',
            ],
            'certification' => ['accepted'],
        ];

        foreach ($definition['fields'] ?? [] as $field) {
            $rules["answers.{$field['key']}"] = ['required', 'string', 'max:10000'];
        }

        return $rules;
    }
}
