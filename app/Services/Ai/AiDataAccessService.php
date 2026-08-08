<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;
use App\Models\User;
use App\Platform\Core\Contracts\LatestContentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiDataAccessService
{
    public function __construct(
        private readonly AiToolRegistry $tools,
        private readonly AiPermissionChecker $permissions,
        private readonly AiAdminReportService $reports,
        private readonly LatestContentProvider $latestContent,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $tool, ?User $user, AiIntent $intent, Request $request, array $input = []): array
    {
        if (! $this->tools->get($tool)) {
            $result = [
                'ok' => false,
                'authorized' => false,
                'error' => 'This AI data tool is not registered.',
            ];
            $this->audit($user, $tool, $intent, false, $result['error'], $input, null, $request);

            return $result;
        }

        if (! $this->permissions->canAccessTool($user, $tool)) {
            $reason = $this->permissions->getToolDeniedReason($user, $tool);
            $this->audit($user, $tool, $intent, false, $reason, $input, null, $request);

            return [
                'ok' => false,
                'authorized' => false,
                'error' => $reason,
            ];
        }

        $result = match ($tool) {
            'users_registered_last_24h' => $this->usersRegisteredLast24h(),
            'user_own_profile' => $this->userOwnProfile($user),
            'platform_basic_stats' => $this->platformBasicStats(),
            'site_content_search' => $this->siteContentSearch((string) ($input['message'] ?? ''), $user),
            'blog_content_search' => $this->blogContentSearch((string) ($input['message'] ?? ''), $user),
            'roles_list' => $this->rolesList(),
            'users_search' => $this->usersSearch((string) ($input['message'] ?? '')),
            default => ['count' => 0, 'items' => []],
        };

        $count = (int) ($result['count'] ?? count($result['items'] ?? []));
        $this->audit($user, $tool, $intent, true, null, $input, $count, $request);

        return [
            'ok' => true,
            'tool' => $tool,
            'authorized' => true,
            'data' => $result,
        ];
    }

    /**
     * @return array{count:int,items:array<int,array<string,mixed>>}
     */
    private function usersRegisteredLast24h(): array
    {
        if (! Schema::hasTable('users')) {
            return ['count' => 0, 'items' => []];
        }

        $items = User::query()
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                'status' => 'active',
            ])
            ->values()
            ->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userOwnProfile(?User $user): array
    {
        if (! $user) {
            return ['count' => 0, 'items' => []];
        }

        return [
            'count' => 1,
            'items' => [[
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                'subscription_plan' => data_get($user, 'subscription_plan', 'default'),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformBasicStats(): array
    {
        return [
            'count' => 1,
            'items' => [$this->reports->basicStats()],
        ];
    }

    /**
     * @return array{count:int,items:array<int,array<string,mixed>>}
     */
    private function siteContentSearch(string $message, ?User $user): array
    {
        if (! Schema::hasTable('platform_pages')) {
            return ['count' => 0, 'items' => []];
        }

        $columns = Schema::getColumnListing('platform_pages');
        $searchColumns = array_values(array_intersect($columns, ['title', 'slug', 'content', 'html', 'body', 'seo_title', 'meta_description']));
        $terms = $this->searchTerms($message);

        $query = DB::table('platform_pages');

        if (in_array('status', $columns, true) && ! $this->permissions->canAccessAdminDashboard($user)) {
            $query->whereIn('status', ['published', 'active']);
        }

        if ($terms !== [] && $searchColumns !== []) {
            $query->where(function ($where) use ($searchColumns, $terms): void {
                foreach ($terms as $term) {
                    $where->orWhere(function ($termQuery) use ($searchColumns, $term): void {
                        foreach ($searchColumns as $column) {
                            $termQuery->orWhere($column, 'like', '%'.$term.'%');
                        }
                    });
                }
            });
        }

        $items = $query
            ->orderByDesc(in_array('updated_at', $columns, true) ? 'updated_at' : (in_array('id', $columns, true) ? 'id' : $columns[0]))
            ->limit(10)
            ->get()
            ->map(function (object $row) use ($columns): array {
                $slug = (string) ($row->slug ?? '');
                $html = (string) ($row->content ?? $row->html ?? $row->body ?? $row->meta_description ?? '');

                return [
                    'id' => $row->id ?? null,
                    'title' => (string) ($row->title ?? $slug ?: 'Untitled page'),
                    'slug' => $slug,
                    'type' => (string) ($row->type ?? 'page'),
                    'status' => in_array('status', $columns, true) ? (string) ($row->status ?? '') : null,
                    'url' => $slug !== '' ? url('/pages/'.$slug) : null,
                    'excerpt' => $this->excerpt($html),
                ];
            })
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @return array{count:int,items:array<int,array<string,mixed>>}
     */
    private function blogContentSearch(string $message, ?User $user): array
    {
        if (! $this->latestContent->available()) {
            return ['count' => 0, 'items' => []];
        }

        $terms = $this->searchTerms($message);
        $items = $this->latestContent
            ->search($terms, $this->permissions->canAccessAdminDashboard($user), 10)
            ->map(function (object $row): array {
                $slug = (string) ($row->slug ?? '');
                $html = (string) ($row->excerpt ?? $row->content ?? $row->body ?? $row->meta_description ?? '');

                return [
                    'id' => $row->id ?? null,
                    'title' => (string) ($row->title ?? $slug ?: 'Untitled post'),
                    'slug' => $slug,
                    'status' => property_exists($row, 'status') ? (string) ($row->status ?? '') : null,
                    'url' => $slug !== '' ? url('/blog/'.$slug) : null,
                    'excerpt' => $this->excerpt($html),
                    'published_at' => property_exists($row, 'published_at') ? (string) ($row->published_at ?? '') : null,
                ];
            })
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @return array{count:int,items:array<int,array<string,mixed>>}
     */
    private function rolesList(): array
    {
        if (! Schema::hasTable('roles')) {
            return ['count' => 0, 'items' => []];
        }

        $items = DB::table('roles')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'guard_name'])
            ->map(function (object $role): array {
                $permissionsCount = Schema::hasTable('role_has_permissions')
                    ? DB::table('role_has_permissions')->where('role_id', $role->id)->count()
                    : 0;

                return [
                    'id' => $role->id,
                    'name' => (string) $role->name,
                    'guard_name' => (string) ($role->guard_name ?? 'web'),
                    'permissions_count' => $permissionsCount,
                ];
            })
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @return array{count:int,items:array<int,array<string,mixed>>}
     */
    private function usersSearch(string $message): array
    {
        if (! Schema::hasTable('users')) {
            return ['count' => 0, 'items' => []];
        }

        $terms = $this->searchTerms($message);
        $query = User::query();

        if ($terms !== []) {
            $query->where(function ($where) use ($terms): void {
                foreach ($terms as $term) {
                    $where->orWhere('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                }
            });
        }

        $items = $query
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
                'status' => 'active',
            ])
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $message): array
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/[^\p{Arabic}A-Za-z0-9\s@._-]+/u', ' ', $message) ?? $message;
        $stopWords = ['ابحث', 'بحث', 'فتش', 'في', 'عن', 'الموقع', 'الصفحات', 'المقالات', 'show', 'search', 'site', 'pages', 'blog', 'posts', 'users', 'roles'];

        return collect(preg_split('/\s+/u', $message) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array(mb_strtolower($term), $stopWords, true))
            ->take(6)
            ->values()
            ->all();
    }

    private function excerpt(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        return mb_substr($text, 0, 500);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function audit(?User $user, string $tool, AiIntent $intent, bool $allowed, ?string $reason, array $input, ?int $resultCount, Request $request): void
    {
        if (! Schema::hasTable('ai_tool_audit_logs')) {
            return;
        }

        DB::table('ai_tool_audit_logs')->insert([
            'user_id' => $user?->id,
            'tool_name' => $tool,
            'intent' => $intent->value,
            'allowed' => $allowed,
            'denied_reason' => $reason,
            'input_summary' => json_encode([
                'message_length' => isset($input['message']) ? mb_strlen((string) $input['message']) : null,
                'plugin' => $input['plugin'] ?? null,
            ], JSON_UNESCAPED_SLASHES),
            'result_count' => $resultCount,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
