<?php

namespace Modules\Blog\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Author;
use Modules\Blog\Models\Post;

final class AccountSectionProvider
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function append(array $sections, User $user): array
    {
        if (! Schema::hasTable('blog_authors') || ! Schema::hasTable('blog_posts')) {
            return $sections;
        }

        $author = Author::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        if (! $author) {
            return $sections;
        }

        $posts = Post::query()
            ->where('author_id', $user->getKey())
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'status', 'review_status', 'updated_at']);

        $sections[] = [
            'key' => 'write-a-post',
            'label' => __('blog::ui.account_posts'),
            'icon' => 'W',
            'order' => 35,
            'badge' => (string) Post::query()->where('author_id', $user->getKey())->count(),
            'view' => 'blog::account.writing',
            'data' => [
                'posts' => $posts,
                'dashboardUrl' => route('blog.writer.index'),
                'createUrl' => route('blog.writer.posts.create'),
                'canPublishDirectly' => $author->canPublishDirectly(),
            ],
        ];

        return $sections;
    }
}
