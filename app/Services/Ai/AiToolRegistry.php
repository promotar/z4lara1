<?php

namespace App\Services\Ai;

class AiToolRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function tools(): array
    {
        return [
            'users_registered_last_24h' => [
                'description' => 'Return users registered in the last 24 hours.',
                'permission' => 'users.viewAny',
                'allowed_fields' => ['id', 'name', 'email', 'created_at', 'status'],
            ],
            'user_own_profile' => [
                'description' => 'Return current authenticated user profile.',
                'permission' => 'authenticated',
                'allowed_fields' => ['id', 'name', 'email', 'created_at', 'subscription_plan'],
            ],
            'platform_basic_stats' => [
                'description' => 'Return basic platform statistics.',
                'permission' => 'admin_dashboard',
                'allowed_fields' => ['users_count', 'new_users_24h', 'orders_count', 'artworks_count'],
            ],
            'site_content_search' => [
                'description' => 'Search published platform pages and public site content.',
                'permission' => 'public',
                'allowed_fields' => ['id', 'title', 'slug', 'type', 'status', 'url', 'excerpt'],
            ],
            'blog_content_search' => [
                'description' => 'Search published blog posts and public articles.',
                'permission' => 'public',
                'allowed_fields' => ['id', 'title', 'slug', 'status', 'url', 'excerpt', 'published_at'],
            ],
            'roles_list' => [
                'description' => 'Return registered platform roles with permission counts.',
                'permission' => 'roles.viewAny',
                'allowed_fields' => ['id', 'name', 'guard_name', 'permissions_count'],
            ],
            'users_search' => [
                'description' => 'Search platform users for authorized admins only.',
                'permission' => 'users.viewAny',
                'allowed_fields' => ['id', 'name', 'email', 'created_at', 'status'],
            ],
        ];
    }

    public function toolForMessage(string $message): ?string
    {
        $message = mb_strtolower($message);

        if ($this->containsAny($message, [
            'مين اليوزرات',
            'المستخدمين المسجلين',
            'اخر 24 ساعة',
            'آخر 24 ساعة',
            'users registered',
            'new users',
            'last 24 hours',
            'user report',
        ])) {
            return 'users_registered_last_24h';
        }

        if ($this->containsAny($message, [
            'كل المستخدمين',
            'ابحث عن مستخدم',
            'فتش عن مستخدم',
            'users search',
            'search users',
            'all users',
        ])) {
            return 'users_search';
        }

        if ($this->containsAny($message, [
            'الرولات',
            'الأدوار',
            'ادوار المستخدمين',
            'قائمة الرولات',
            'roles list',
            'user roles',
        ])) {
            return 'roles_list';
        }

        if ($this->containsAny($message, [
            'احصائيات المنصة',
            'إحصائيات المنصة',
            'عدد المستخدمين',
            'platform stats',
            'admin report',
        ])) {
            return 'platform_basic_stats';
        }

        if ($this->containsAny($message, [
            'ملفي',
            'بروفايلي',
            'حسابي',
            'my profile',
            'my account',
        ])) {
            return 'user_own_profile';
        }

        if ($this->containsAny($message, [
            'مقال',
            'مقالات',
            'المدونة',
            'البوستات',
            'blog',
            'article',
            'articles',
            'posts',
        ])) {
            return 'blog_content_search';
        }

        if ($this->containsAny($message, [
            'ابحث في الموقع',
            'بحث في الموقع',
            'فتش في الموقع',
            'فتش في الصفحات',
            'ابحث في الصفحات',
            'معلومة في الموقع',
            'الصفحات',
            'site search',
            'search the site',
            'search pages',
            'page content',
        ])) {
            return 'site_content_search';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $tool): ?array
    {
        return $this->tools()[$tool] ?? null;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
