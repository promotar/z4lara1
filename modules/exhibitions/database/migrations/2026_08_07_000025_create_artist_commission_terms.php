<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exhibition_artist_commission_terms')) {
            Schema::create('exhibition_artist_commission_terms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('artist_identity_id')->constrained('exhibition_artist_identities')->cascadeOnDelete();
                $table->string('source', 32);
                $table->unsignedSmallInteger('commission_basis_points')->nullable();
                $table->timestamp('effective_from');
                $table->text('reason');
                // Core user identifiers are stable references across the module boundary;
                // no cross-module foreign key is allowed here.
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['artist_identity_id', 'effective_from'], 'ex_artist_commission_effective_idx');
            });
        }

        if (Schema::hasColumn('exhibition_artist_identities', 'commission_rate')) {
            foreach (DB::table('exhibition_artist_identities')->whereNotNull('commission_rate')->orderBy('id')->get() as $identity) {
                $rate = number_format((float) $identity->commission_rate, 3, '.', '');
                [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
                $basisPoints = intdiv(((int) $whole * 1000) + (int) str_pad($fraction, 3, '0') + 5, 10);

                DB::table('exhibition_artist_commission_terms')->insertOrIgnore([
                    'public_id' => (string) Str::uuid(),
                    'artist_identity_id' => $identity->id,
                    'source' => 'custom',
                    'commission_basis_points' => $basisPoints,
                    'effective_from' => $identity->updated_at ?? now(),
                    'reason' => 'Migrated from the legacy artist commission field.',
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibition_artist_commission_terms');
    }
};
