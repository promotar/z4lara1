<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiActionExecutor
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(User $user, AiIntent $intent, array $data): array
    {
        return match ($intent) {
            AiIntent::UpdateProfile => $this->updateProfile($user, $data),
            default => [
                'ok' => false,
                'message' => 'This sensitive action is not implemented yet.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updateProfile(User $user, array $data): array
    {
        $field = (string) ($data['field'] ?? '');
        $value = trim((string) ($data['value'] ?? ''));

        if (! in_array($field, ['name', 'email'], true)) {
            return ['ok' => false, 'message' => 'Only name and email profile updates are supported right now.'];
        }

        if ($value === '') {
            return ['ok' => false, 'message' => 'Missing profile value.'];
        }

        if ($field === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Invalid email address.']);
        }

        DB::transaction(function () use ($user, $field, $value): void {
            $user->forceFill([$field => $value])->save();
        });

        return [
            'ok' => true,
            'message' => 'Profile updated successfully.',
            'data' => ['field' => $field],
        ];
    }
}
