<?php

namespace Modules\Store\Application\Customer;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class CustomerProfileService
{
    public function find(int $userId): object
    {
        return DB::table('store_customer_profiles')->where('user_id', $userId)->first()
            ?? (object) [
                'user_id' => $userId,
                'phone' => null,
                'avatar_path' => null,
                'updated_at' => null,
            ];
    }

    /** @param array<string, mixed> $data */
    public function update(int $userId, array $data, ?UploadedFile $avatar): object
    {
        $profile = $this->find($userId);
        $oldAvatarPath = $profile->avatar_path;
        $newAvatarPath = null;

        if ($avatar !== null) {
            $extension = strtolower((string) ($avatar->extension() ?: 'jpg'));
            $newAvatarPath = $avatar->storeAs(
                'store/customer-avatars/'.$userId,
                Str::lower((string) Str::ulid()).'.'.$extension,
                'local',
            );

            if ($newAvatarPath === false) {
                throw new \RuntimeException('The profile image could not be saved. Please try again.');
            }
        }

        $avatarPath = $newAvatarPath
            ?? ((bool) ($data['remove_avatar'] ?? false) ? null : $oldAvatarPath);

        try {
            DB::transaction(function () use ($userId, $data, $avatarPath): void {
                DB::table('store_customer_profiles')->upsert([[
                    'user_id' => $userId,
                    'phone' => $this->nullable($data['phone'] ?? null),
                    'avatar_path' => $avatarPath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['user_id'], ['phone', 'avatar_path', 'updated_at']);
            });
        } catch (Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('local')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($oldAvatarPath !== null && $oldAvatarPath !== $avatarPath) {
            Storage::disk('local')->delete((string) $oldAvatarPath);
        }

        return $this->find($userId);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
