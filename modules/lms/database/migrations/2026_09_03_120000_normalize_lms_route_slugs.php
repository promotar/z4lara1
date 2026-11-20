<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->normalizeTable('lms_courses', 'course');
            $this->normalizeTable('lms_lessons', 'lesson');
        });
    }

    public function down(): void
    {
        // The legacy values were URL-encoded route fragments and could not be
        // resolved by Laravel after the web server decoded the request path.
        // Keeping the valid Latin slugs is safer than restoring broken URLs.
    }

    private function normalizeTable(string $table, string $fallbackPrefix): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumns($table, ['id', 'title', 'slug'])) {
            return;
        }

        $used = DB::table($table)
            ->pluck('slug')
            ->filter(fn ($slug): bool => is_string($slug) && $slug !== '')
            ->mapWithKeys(fn (string $slug): array => [$slug => true])
            ->all();

        DB::table($table)
            ->select(['id', 'title', 'slug'])
            ->orderBy('id')
            ->chunkById(250, function ($records) use ($table, $fallbackPrefix, &$used): void {
                foreach ($records as $record) {
                    $current = trim((string) $record->slug);

                    if ($this->isStandardSlug($current)) {
                        continue;
                    }

                    unset($used[$current]);

                    $base = $this->englishSlug((string) $record->title);
                    if ($base === '') {
                        $base = $fallbackPrefix.'-'.$record->id;
                    }

                    $candidate = $base;
                    $suffix = 2;

                    while (isset($used[$candidate])) {
                        $suffixText = '-'.$suffix++;
                        $candidate = substr($base, 0, 220 - strlen($suffixText)).$suffixText;
                    }

                    DB::table($table)->where('id', $record->id)->update([
                        'slug' => $candidate,
                        'updated_at' => now(),
                    ]);

                    $used[$candidate] = true;
                }
            });
    }

    private function isStandardSlug(string $slug): bool
    {
        return preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) === 1;
    }

    private function englishSlug(string $value): string
    {
        $map = [
            'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ب' => 'b', 'ت' => 't',
            'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
            'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd',
            'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
            'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'ة' => 'h',
            'و' => 'w', 'ؤ' => 'w', 'ي' => 'y', 'ى' => 'a', 'ئ' => 'y', 'ء' => '',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        return substr(Str::slug(strtr($value, $map)), 0, 220);
    }
};
