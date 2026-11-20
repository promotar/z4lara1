<?php

namespace Modules\Blog\Services;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BlogUserDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'blog';
    }

    public function blockers(User $user): array
    {
        $checks = [
            ['blog_posts', 'author_id', 'authored post'],
            ['blog_post_revisions', 'user_id', 'post revision'],
            ['blog_media', 'uploaded_by', 'media upload'],
        ];

        $blockers = [];
        foreach ($checks as [$table, $column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $user->getKey())->count();
            if ($count > 0) {
                $blockers[] = "has {$count} {$label}".($count === 1 ? '' : 's');
            }
        }

        return $blockers;
    }

    public function cleanup(User $user): void
    {
        if (Schema::hasTable('blog_authors')) {
            DB::table('blog_authors')->where('user_id', $user->getKey())->delete();
        }

        // Publishing authorship and revision history is retained until an
        // administrator explicitly reassigns or removes those records.
    }
}
