<?php

namespace Modules\Exhibition\Infrastructure\Identity;

use App\Models\User;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;

final class EloquentCoreUserGateway implements CoreUserGateway
{
    public function find(int $userId): ?object
    {
        return User::query()->find($userId);
    }

    public function findByEmail(string $email): ?object
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    public function paginateExcept(array $excludedUserIds, string $search, int $perPage): object
    {
        $needle = trim($search);

        return User::query()
            ->when($excludedUserIds !== [], fn ($query) => $query->whereNotIn('id', $excludedUserIds))
            ->when($needle !== '', function ($query) use ($needle): void {
                $query->where(function ($query) use ($needle): void {
                    $query->where('name', 'like', '%'.$needle.'%')
                        ->orWhere('email', 'like', '%'.$needle.'%');
                });
            })
            ->select('id', 'name', 'email', 'email_verified_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(100, max(10, $perPage)))
            ->withQueryString();
    }

    public function findMany(array $userIds): array
    {
        return User::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $userIds))))
            ->get(['id', 'name', 'email', 'email_verified_at'])
            ->keyBy('id')
            ->all();
    }

    public function emailIsAvailable(string $email): bool
    {
        return ! User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->exists();
    }

    public function create(string $name, string $email, string $password): object
    {
        return User::query()->create([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
        ]);
    }

    public function ensureRole(int $userId, string $role): void
    {
        $user = User::query()->findOrFail($userId);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }

    public function removeRole(int $userId, string $role): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->hasRole($role)) {
            $user->removeRole($role);
        }
    }
}
