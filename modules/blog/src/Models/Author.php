<?php

namespace Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Author extends Model
{
    protected $table = 'blog_authors';

    protected $fillable = [
        'user_id',
        'status',
        'can_publish_directly',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'can_publish_directly' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canPublishDirectly(): bool
    {
        return $this->isActive() && $this->can_publish_directly === true;
    }
}
