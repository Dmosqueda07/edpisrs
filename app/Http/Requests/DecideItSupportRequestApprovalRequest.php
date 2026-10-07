<?php

namespace App\Http\Requests;

use App\Enums\ApprovalDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideItSupportRequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ApprovalDecision::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
