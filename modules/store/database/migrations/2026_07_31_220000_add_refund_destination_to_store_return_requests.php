<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumns('store_return_requests', [
            'refund_destination_method',
            'refund_destination_encrypted',
            'refund_destination_submitted_at',
            'refund_transfer_reference',
            'refund_transfer_note',
            'refund_sent_at',
            'refund_received_at',
        ])) {
            return;
        }

        Schema::table('store_return_requests', function (Blueprint $table): void {
            $table->string('refund_destination_method', 32)->nullable()->after('refund_method');
            $table->text('refund_destination_encrypted')->nullable()->after('refund_destination_method');
            $table->timestamp('refund_destination_submitted_at')->nullable()->after('refund_destination_encrypted');
            $table->string('refund_transfer_reference', 128)->nullable()->after('refund_destination_submitted_at');
            $table->text('refund_transfer_note')->nullable()->after('refund_transfer_reference');
            $table->timestamp('refund_sent_at')->nullable()->after('refund_transfer_note');
            $table->timestamp('refund_received_at')->nullable()->after('refund_sent_at');
        });

        DB::table('store_return_requests')
            ->where('status', 'refunded')
            ->whereIn('resolution_type', ['full_refund', 'partial_refund'])
            ->update([
                'status' => 'refund_details_required',
                'refund_method' => null,
                'refunded_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('store_return_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'refund_destination_method',
                'refund_destination_encrypted',
                'refund_destination_submitted_at',
                'refund_transfer_reference',
                'refund_transfer_note',
                'refund_sent_at',
                'refund_received_at',
            ]);
        });
    }
};
