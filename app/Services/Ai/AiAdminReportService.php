<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiAdminReportService
{
    /**
     * @return array<string, int|null>
     */
    public function basicStats(): array
    {
        return [
            'users_count' => Schema::hasTable('users') ? DB::table('users')->count() : null,
            'new_users_24h' => Schema::hasTable('users') ? DB::table('users')->where('created_at', '>=', now()->subDay())->count() : null,
            'orders_count' => Schema::hasTable('orders') ? DB::table('orders')->count() : null,
            'artworks_count' => Schema::hasTable('artworks') ? DB::table('artworks')->count() : null,
        ];
    }
}
