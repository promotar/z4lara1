<?php

namespace App\Platform\Core\Accounts;

use App\Models\User;
use App\Platform\Core\Hooks\HookManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final class AccountSectionRegistry
{
    public function __construct(private readonly HookManager $hooks) {}

    /**
     * Plugins extend the account without making core aware of their models,
     * tables, routes, or views. A callback receives the list and current user.
     *
     * @return list<array{key:string,label:string,icon:string,order:int,badge:?string,view:string,data:array<string,mixed>}>
     */
    public function sections(User $user): array
    {
        $sections = [[
            'key' => 'overview',
            'label' => \App\Platform\Core\Localization\Translation::label('core.nav.overview', 'Overview'),
            'icon' => 'A',
            'order' => 10,
            'badge' => null,
            'view' => 'frontend.account-sections.overview',
            'data' => [],
        ], [
            // Profile lives inside the account shell so the user never leaves
            // the account sidebar when editing their own data.
            'key' => 'profile',
            'label' => \App\Platform\Core\Localization\Translation::label('core.account.profile', 'Profile'),
            'icon' => 'P',
            'order' => 15,
            'badge' => null,
            'view' => 'frontend.account-sections.profile',
            'data' => [],
        ]];

        $filtered = $this->hooks->applyFilters('frontend.account.sections', $sections, $user);

        return collect(is_array($filtered) ? $filtered : $sections)
            ->map(fn (mixed $section): ?array => $this->normalize($section))
            ->filter()
            ->unique('key')
            ->sortBy([['order', 'asc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /** @return array{key:string,label:string,icon:string,order:int,badge:?string,view:string,data:array<string,mixed>}|null */
    private function normalize(mixed $section): ?array
    {
        if (! is_array($section)) {
            return null;
        }

        $key = Str::slug((string) ($section['key'] ?? ''));
        $label = trim((string) ($section['label'] ?? ''));
        $view = trim((string) ($section['view'] ?? ''));

        if ($key === '' || $label === '' || $view === '' || ! View::exists($view)) {
            return null;
        }

        $badge = $section['badge'] ?? null;

        return [
            'key' => $key,
            'label' => Str::limit($label, 80, ''),
            'icon' => Str::limit(trim((string) ($section['icon'] ?? Str::upper(Str::substr($label, 0, 1)))), 4, ''),
            'order' => max(-10000, min(10000, (int) ($section['order'] ?? 100))),
            'badge' => $badge === null ? null : Str::limit((string) $badge, 24, ''),
            'view' => $view,
            'data' => is_array($section['data'] ?? null) ? Arr::except($section['data'], ['user']) : [],
        ];
    }
}
