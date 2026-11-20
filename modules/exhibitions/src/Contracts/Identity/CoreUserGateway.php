<?php

namespace Modules\Exhibition\Contracts\Identity;

interface CoreUserGateway
{
    public function find(int $userId): ?object;

    public function findByEmail(string $email): ?object;

    /** @param list<int> $excludedUserIds */
    public function paginateExcept(array $excludedUserIds, string $search, int $perPage): object;

    /** @param list<int> $userIds @return array<int, object> */
    public function findMany(array $userIds): array;

    public function emailIsAvailable(string $email): bool;

    public function create(string $name, string $email, string $password): object;

    public function ensureRole(int $userId, string $role): void;

    public function removeRole(int $userId, string $role): void;
}
