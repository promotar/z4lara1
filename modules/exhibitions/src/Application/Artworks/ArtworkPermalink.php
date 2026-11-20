<?php

namespace Modules\Exhibition\Application\Artworks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ArtworkPermalink
{
    public function ensure(object $artwork): object
    {
        if (! Schema::hasColumn('exhibition_artworks', 'slug') || filled($artwork->slug ?? null)) {
            return $artwork;
        }

        $base = Str::slug((string) $artwork->title);
        if ($base === '') {
            $base = trim((string) preg_replace('/[^\pL\pN]+/u', '-', Str::lower((string) $artwork->title)), '-');
        }
        $base = Str::limit($base ?: 'artwork', 170, '');
        $candidate = $base;
        if (DB::table('exhibition_artworks')->where('id', '!=', $artwork->id)->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.Str::lower(substr(str_replace('-', '', (string) $artwork->public_id), 0, 8));
        }

        DB::table('exhibition_artworks')->where('id', $artwork->id)->update(['slug' => $candidate]);
        $artwork->slug = $candidate;

        return $artwork;
    }
}
