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
        $requestType = RequestType::query()->find($this->input('request_type_id'));
        $definition = collect(config('request_forms.request_types'))
            ->firstWhere('key', $requestType?->key);
        $allowedAnswerKeys = collect($definition['fields'] ?? [])
            ->pluck('key')
            ->all();
        $rules = [
            'summary' => ['required', 'string', 'max:120'],
            'request_type_id' => [
                'required',
                'integer',
                Rule::exists('request_types', 'request_types_id')->where('is_active', true),
            ],
            'answers' => $allowedAnswerKeys === []
                ? ['required', 'array', Rule::in([])]
                : ['required', 'array:'.implode(',', $allowedAnswerKeys)],
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
