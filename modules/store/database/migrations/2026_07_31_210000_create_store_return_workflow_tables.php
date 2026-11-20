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
        if (Schema::hasTable('store_return_requests')
            && Schema::hasTable('store_return_request_attachments')
            && Schema::hasTable('store_return_request_events')) {
            return;
        }

        Schema::create('store_return_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('status', 32)->default('requested')->index();
            $table->text('reason');
            $table->string('reported_condition', 32);
            $table->string('preferred_resolution', 32)->default('refund');
            $table->string('rma_number', 64)->nullable()->unique();
            $table->text('customer_message')->nullable();
            $table->text('admin_response')->nullable();
            $table->text('return_instructions')->nullable();
            $table->text('return_address')->nullable();
            $table->timestamp('shipping_deadline')->nullable();
            $table->string('carrier', 128)->nullable();
            $table->string('tracking_number', 128)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('evidence_submitted_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('inspection_condition', 32)->nullable();
            $table->text('inspection_notes')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->string('resolution_type', 32)->nullable();
            $table->unsignedBigInteger('refund_amount_minor')->nullable();
            $table->string('refund_method', 64)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->boolean('restore_inventory')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('store_return_request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('return_request_id')->constrained('store_return_requests')->cascadeOnDelete();
            $table->string('uploader_type', 16);
            $table->string('uploader_id', 64)->nullable();
            $table->string('kind', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index(['return_request_id', 'kind']);
        });

        Schema::create('store_return_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_request_id')->constrained('store_return_requests')->cascadeOnDelete();
            $table->string('event_type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('actor_type', 32);
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('visible_to_customer')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['return_request_id', 'created_at']);
        });

        $this->backfillExistingRequests();
    }

    public function down(): void
    {
        Schema::dropIfExists('store_return_request_events');
        Schema::dropIfExists('store_return_request_attachments');
        Schema::dropIfExists('store_return_requests');
    }

    private function backfillExistingRequests(): void
    {
        if (! Schema::hasTable('store_order_events')) {
            return;
        }

        foreach (DB::table('store_order_events')->where('event_type', 'return_requested')->orderBy('id')->get() as $event) {
            $later = DB::table('store_order_events')
                ->where('order_id', $event->order_id)
                ->where('id', '>', $event->id)
                ->whereIn('event_type', ['return_rejected', 'status_changed'])
                ->orderBy('id')
                ->first();
            $metadata = filled($event->metadata ?? null) ? json_decode((string) $event->metadata, true) : [];
            $status = $later?->event_type === 'return_rejected'
                ? 'rejected'
                : (($later?->to_status ?? null) === 'returned' ? 'returned' : 'requested');
            $requestId = DB::table('store_return_requests')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'order_id' => $event->order_id,
                'customer_id' => is_numeric($event->actor_id ?? null) ? (int) $event->actor_id : null,
                'status' => $status,
                'reason' => (string) ($event->message ?? 'Customer return request.'),
                'reported_condition' => (string) (($metadata['condition'] ?? null) ?: 'unknown'),
                'preferred_resolution' => 'refund',
                'admin_response' => $later?->event_type === 'return_rejected' ? (string) $later->message : null,
                'approved_at' => $status === 'returned' ? $later?->created_at : null,
                'closed_at' => in_array($status, ['rejected', 'returned'], true) ? $later?->created_at : null,
                'created_at' => $event->created_at,
                'updated_at' => $later?->created_at ?? $event->created_at,
            ]);

            DB::table('store_return_request_events')->insert([
                'return_request_id' => $requestId,
                'event_type' => 'requested',
                'from_status' => null,
                'to_status' => 'requested',
                'actor_type' => (string) ($event->actor_type ?? 'user'),
                'actor_id' => $event->actor_id,
                'actor_name' => $event->actor_name,
                'message' => (string) ($event->message ?? 'Customer return request.'),
                'metadata' => json_encode(['condition' => (string) (($metadata['condition'] ?? null) ?: 'unknown')]),
                'visible_to_customer' => true,
                'created_at' => $event->created_at,
            ]);
        }
    }
};
