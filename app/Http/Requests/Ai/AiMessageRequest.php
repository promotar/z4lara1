<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            'plugin' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'context' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['array'],
        ];
    }
}
