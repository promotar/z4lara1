<?php

namespace Modules\Blog\Services;

use App\Models\User;
use Modules\Blog\Models\Author;
use Modules\Blog\Models\Post;

final class AuthorAccessService
{
    public function activeAuthor(User $user): Author
    {
        $author = Author::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        abort_unless($author, 403, 'Your Blog author access is not active.');

        return $author;
    }

    public function ensureOwnsPost(User $user, Post $post): void
    {
        abort_unless((int) $post->author_id === (int) $user->getKey(), 404);
    }
}
