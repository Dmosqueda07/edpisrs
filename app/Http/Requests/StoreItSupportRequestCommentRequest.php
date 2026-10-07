<?php

namespace App\Http\Requests;

use App\Models\ItSupportRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreItSupportRequestCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supportRequest = $this->route('it_support_request');
        $user = $this->user();

        if (! $supportRequest instanceof ItSupportRequest || ! $user?->can('comment', $supportRequest)) {
            return false;
        }

        return ! $this->hasFile('proof') || $user->can('addProof', $supportRequest);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ];
    }
}
