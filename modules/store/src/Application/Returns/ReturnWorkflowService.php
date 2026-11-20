<?php

namespace Modules\Store\Application\Returns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Store\Application\Customer\CustomerResourceNotFound;
use Modules\Store\Application\Orders\OrderEventRecorder;
use Modules\Store\Application\Orders\OrderWorkflowService;
use Modules\Store\Application\Orders\StoreAdminNotificationService;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Domain\Shared\ActorContext;

final readonly class ReturnWorkflowService
{
    /** @var list<string> */
    private const ACTIVE_STATUSES = [
        'requested', 'evidence_required', 'under_review', 'approved',
        'in_transit', 'received', 'inspection', 'returned',
        'refund_details_required', 'refund_details_submitted', 'refund_sent', 'refund_received',
    ];

    public function __construct(
        private OrderEventRecorder $orderEvents,
        private OrderWorkflowService $orders,
        private StoreAdminNotificationService $notifications,
    ) {}

    public function createRequest(
        int $customerId,
        string $orderPublicId,
        string $reason,
        string $condition,
        string $preferredResolution,
        string $customerName,
    ): object {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A return reason is required.');
        }

        $request = DB::transaction(function () use ($customerId, $orderPublicId, $reason, $condition, $preferredResolution, $customerName): object {
            $order = DB::table('store_orders')
                ->where('public_id', $orderPublicId)
                ->where('created_by', $customerId)
                ->lockForUpdate()
                ->first();
            if ($order === null) {
                throw new CustomerResourceNotFound('Order does not exist.');
            }
            if ((string) $order->status !== OrderStatus::Delivered->value) {
                throw new InvalidArgumentException('A return can only be requested after the order is delivered.');
            }
            if ($this->activeForOrder((int) $order->id) !== null) {
                throw new InvalidArgumentException('A return process is already active for this order.');
            }

            $publicId = (string) Str::ulid();
            $id = DB::table('store_return_requests')->insertGetId([
                'public_id' => $publicId,
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'status' => 'requested',
                'reason' => $reason,
                'reported_condition' => $condition,
                'preferred_resolution' => $preferredResolution,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $actor = new ActorContext('user', (string) $customerId, $customerName);
            $this->record($id, 'requested', null, 'requested', $actor, $reason, [
                'condition' => $condition,
                'preferred_resolution' => $preferredResolution,
            ]);
            $this->orderEvents->record(
                (int) $order->id,
                'return_requested',
                $actor,
                $reason,
                (string) $order->status,
                (string) $order->status,
                ['condition' => $condition, 'preferred_resolution' => $preferredResolution, 'return_request_public_id' => $publicId],
                'customer',
            );

            return DB::table('store_return_requests')->where('id', $id)->first();
        });

        $this->notifications->send(
            'return_request',
            "Return requested for {$orderPublicId}",
            'A customer submitted a return request for review.',
        );

        return $request;
    }

    /** @param list<UploadedFile> $photos */
    public function submitEvidence(
        int $customerId,
        string $orderPublicId,
        array $photos,
        ?UploadedFile $video,
        ?string $message,
        string $customerName,
    ): void {
        $request = $this->ownedActiveRequest($customerId, $orderPublicId);
        if (! in_array((string) $request->status, ['evidence_required', 'under_review'], true)) {
            throw new InvalidArgumentException('Evidence cannot be submitted at the current return stage.');
        }

        foreach ($photos as $photo) {
            $this->storeAttachment($request, $photo, 'product_photo', 'customer', (string) $customerId);
        }
        if ($video !== null) {
            $this->storeAttachment($request, $video, 'condition_video', 'customer', (string) $customerId);
        }
        if (DB::table('store_return_request_attachments')->where('return_request_id', $request->id)->count() === 0) {
            throw new InvalidArgumentException('Upload at least one photo or video showing the product condition.');
        }

        DB::transaction(function () use ($request, $message, $customerId, $customerName): void {
            $current = DB::table('store_return_requests')->where('id', $request->id)->lockForUpdate()->first();
            $this->assertStatus($current, ['evidence_required', 'under_review']);
            DB::table('store_return_requests')->where('id', $current->id)->update([
                'status' => 'under_review',
                'customer_message' => $this->nullable($message),
                'evidence_submitted_at' => now(),
                'updated_at' => now(),
            ]);
            $this->record(
                (int) $current->id,
                'evidence_submitted',
                (string) $current->status,
                'under_review',
                new ActorContext('user', (string) $customerId, $customerName),
                $this->nullable($message) ?? 'The customer submitted product-condition evidence.',
            );
        });
    }

    public function submitShipment(
        int $customerId,
        string $orderPublicId,
        string $carrier,
        string $trackingNumber,
        ?UploadedFile $receipt,
        string $customerName,
    ): void {
        $request = $this->ownedActiveRequest($customerId, $orderPublicId);
        $this->assertStatus($request, ['approved']);
        if ($receipt !== null) {
            $this->storeAttachment($request, $receipt, 'shipping_receipt', 'customer', (string) $customerId);
        }

        DB::transaction(function () use ($request, $carrier, $trackingNumber, $customerId, $customerName): void {
            $current = DB::table('store_return_requests')->where('id', $request->id)->lockForUpdate()->first();
            $this->assertStatus($current, ['approved']);
            DB::table('store_return_requests')->where('id', $current->id)->update([
                'status' => 'in_transit',
                'carrier' => trim($carrier),
                'tracking_number' => trim($trackingNumber),
                'shipped_at' => now(),
                'updated_at' => now(),
            ]);
            $this->record(
                (int) $current->id,
                'shipment_submitted',
                'approved',
                'in_transit',
                new ActorContext('user', (string) $customerId, $customerName),
                'The customer shipped the return.',
                ['carrier' => trim($carrier), 'tracking_number' => trim($trackingNumber)],
            );
        });
    }

    public function cancelByCustomer(int $customerId, string $orderPublicId, string $customerName): void
    {
        $request = $this->ownedActiveRequest($customerId, $orderPublicId);
        $this->assertStatus($request, ['requested', 'evidence_required', 'under_review', 'approved']);
        $this->changeStatus(
            $request,
            'customer_cancelled',
            new ActorContext('user', (string) $customerId, $customerName),
            'The customer cancelled the return request.',
            ['closed_at' => now()],
        );
    }

    /** @param array<string, mixed> $data */
    public function submitRefundDestination(
        int $customerId,
        string $orderPublicId,
        array $data,
        string $customerName,
    ): void {
        $request = $this->ownedActiveRequest($customerId, $orderPublicId);
        $this->assertStatus($request, ['refund_details_required']);

        $method = trim((string) ($data['refund_destination_method'] ?? ''));
        if (! in_array($method, ['bank_transfer', 'cliq'], true)) {
            throw new InvalidArgumentException('Select Bank Transfer or CliQ as the refund method.');
        }

        $destination = $this->validatedRefundDestination($method, $data);
        $encrypted = Crypt::encryptString(json_encode($destination, JSON_THROW_ON_ERROR));
        $label = $method === 'bank_transfer' ? 'Bank Transfer' : 'CliQ';

        $this->changeStatus(
            $request,
            'refund_details_submitted',
            new ActorContext('user', (string) $customerId, $customerName),
            "The customer submitted {$label} refund details.",
            [
                'refund_destination_method' => $method,
                'refund_destination_encrypted' => $encrypted,
                'refund_destination_submitted_at' => now(),
            ],
            ['refund_destination_method' => $method],
        );
    }

    public function confirmRefundReceived(int $customerId, string $orderPublicId, string $customerName): void
    {
        $request = $this->ownedActiveRequest($customerId, $orderPublicId);
        $this->assertStatus($request, ['refund_sent']);
        $this->changeStatus(
            $request,
            'refund_received',
            new ActorContext('user', (string) $customerId, $customerName),
            'The customer confirmed receiving the refund.',
            ['refund_received_at' => now()],
        );
    }

    /** @param array<string, mixed> $data */
    public function adminAction(string $orderPublicId, string $action, array $data, ActorContext $actor): void
    {
        $order = DB::table('store_orders')->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            throw new InvalidArgumentException('Order does not exist.');
        }
        $request = $this->activeForOrder((int) $order->id);
        if ($request === null) {
            throw new InvalidArgumentException('There is no active return process for this order.');
        }

        match ($action) {
            'approve_process' => $this->approveProcess($request, $data, $actor),
            'request_more_evidence' => $this->requestMoreEvidence($request, $data, $actor),
            'authorize_return' => $this->authorizeReturn($request, $data, $actor),
            'reject' => $this->reject($request, $data, $actor),
            'mark_received' => $this->markReceived($request, $actor),
            'start_inspection' => $this->startInspection($request, $actor),
            'accept_after_inspection' => $this->acceptAfterInspection($order, $request, $data, $actor),
            'complete_resolution' => $this->completeResolution($order, $request, $data, $actor),
            'record_refund_sent' => $this->recordRefundSent($request, $data, $actor),
            'close' => $this->close($request, $data, $actor),
            'expire' => $this->expire($request, $data, $actor),
            default => throw new InvalidArgumentException('Unsupported return action.'),
        };
    }

    /** @return array<string, mixed> */
    public function viewData(int $orderId): array
    {
        $request = DB::table('store_return_requests')->where('order_id', $orderId)->latest('id')->first();
        $refundDestination = [];
        if ($request !== null && filled($request->refund_destination_encrypted ?? null)) {
            try {
                $decoded = json_decode(Crypt::decryptString((string) $request->refund_destination_encrypted), true, 16, JSON_THROW_ON_ERROR);
                $refundDestination = is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                $refundDestination = [];
            }
        }

        return [
            'return_process' => $request,
            'refund_destination' => $refundDestination,
            'return_process_events' => $request === null
                ? collect()
                : DB::table('store_return_request_events')->where('return_request_id', $request->id)->orderBy('id')->get(),
            'return_process_attachments' => $request === null
                ? collect()
                : DB::table('store_return_request_attachments')->where('return_request_id', $request->id)->orderBy('id')->get(),
        ];
    }

    public function attachmentForCustomer(int $customerId, string $orderPublicId, string $attachmentPublicId): object
    {
        $attachment = $this->attachmentQuery()
            ->where('orders.created_by', $customerId)
            ->where('orders.public_id', $orderPublicId)
            ->where('attachment.public_id', $attachmentPublicId)
            ->first();

        return $attachment ?? throw new CustomerResourceNotFound('Return attachment does not exist.');
    }

    public function attachmentForAdmin(string $orderPublicId, string $attachmentPublicId): object
    {
        $attachment = $this->attachmentQuery()
            ->where('orders.public_id', $orderPublicId)
            ->where('attachment.public_id', $attachmentPublicId)
            ->first();

        return $attachment ?? throw new InvalidArgumentException('Return attachment does not exist.');
    }

    public function activeForOrder(int $orderId): ?object
    {
        return DB::table('store_return_requests')
            ->where('order_id', $orderId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    private function ownedActiveRequest(int $customerId, string $orderPublicId): object
    {
        $request = DB::table('store_return_requests as request')
            ->join('store_orders as orders', 'orders.id', '=', 'request.order_id')
            ->where('orders.created_by', $customerId)
            ->where('orders.public_id', $orderPublicId)
            ->whereIn('request.status', self::ACTIVE_STATUSES)
            ->select('request.*')
            ->latest('request.id')
            ->first();

        return $request ?? throw new CustomerResourceNotFound('Active return request does not exist.');
    }

    /** @param array<string, mixed> $data */
    private function approveProcess(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['requested']);
        $message = $this->nullable($data['message'] ?? null) ?? 'Your request was approved for evidence review. Upload clear photos and an optional video.';
        $this->changeStatus($request, 'evidence_required', $actor, $message, ['admin_response' => $message]);
    }

    /** @param array<string, mixed> $data */
    private function requestMoreEvidence(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['under_review', 'evidence_required']);
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            throw new InvalidArgumentException('Explain which additional evidence is required.');
        }
        $this->changeStatus($request, 'evidence_required', $actor, $message, ['admin_response' => $message]);
    }

    /** @param array<string, mixed> $data */
    private function authorizeReturn(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['under_review']);
        $instructions = trim((string) ($data['return_instructions'] ?? ''));
        $address = trim((string) ($data['return_address'] ?? ''));
        if ($instructions === '' || $address === '') {
            throw new InvalidArgumentException('Return instructions and the return address are required.');
        }
        $deadline = $data['shipping_deadline'] ?? now()->addDays(7);
        $rma = 'RMA-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        $this->changeStatus($request, 'approved', $actor, 'The return was authorized. Ship it before the displayed deadline.', [
            'rma_number' => $rma,
            'return_instructions' => $instructions,
            'return_address' => $address,
            'shipping_deadline' => $deadline,
            'approved_at' => now(),
            'admin_response' => $this->nullable($data['message'] ?? null),
        ], ['rma_number' => $rma]);
    }

    /** @param array<string, mixed> $data */
    private function reject(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['under_review']);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('A customer-facing rejection reason is required.');
        }
        $this->changeStatus($request, 'rejected', $actor, $reason, ['admin_response' => $reason, 'closed_at' => now()]);
    }

    private function markReceived(object $request, ActorContext $actor): void
    {
        $this->assertStatus($request, ['in_transit']);
        $this->changeStatus($request, 'received', $actor, 'The returned parcel was received by the store.', ['received_at' => now()]);
    }

    private function startInspection(object $request, ActorContext $actor): void
    {
        $this->assertStatus($request, ['received']);
        $this->changeStatus($request, 'inspection', $actor, 'The returned product is being inspected.');
    }

    /** @param array<string, mixed> $data */
    private function acceptAfterInspection(object $order, object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['inspection']);
        $condition = trim((string) ($data['inspection_condition'] ?? ''));
        $notes = trim((string) ($data['inspection_notes'] ?? ''));
        $resolution = trim((string) ($data['resolution_type'] ?? ''));
        if ($condition === '' || $notes === '' || $resolution === '') {
            throw new InvalidArgumentException('Inspection condition, notes, and resolution are required.');
        }

        $restore = (bool) ($data['restore_inventory'] ?? false);
        DB::transaction(function () use ($order, $request, $data, $actor, $condition, $notes, $resolution, $restore): void {
            $current = DB::table('store_return_requests')->where('id', $request->id)->lockForUpdate()->first();
            $this->assertStatus($current, ['inspection']);

            $this->orders->transition(
                (string) $order->public_id,
                OrderStatus::Returned,
                $actor,
                [
                    'reason' => $notes,
                    'return_condition' => $condition,
                    'return_to_stock' => $restore,
                    'return_request_public_id' => $request->public_id,
                ],
            );
            $this->changeStatus($current, 'returned', $actor, 'The return passed inspection and was accepted.', [
                'inspection_condition' => $condition,
                'inspection_notes' => $notes,
                'inspected_at' => now(),
                'resolution_type' => $resolution,
                'refund_amount_minor' => isset($data['refund_amount']) && $data['refund_amount'] !== ''
                    ? $this->minor($data['refund_amount'], (string) $order->currency)
                    : null,
                'restore_inventory' => $restore,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function completeResolution(object $order, object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['returned']);
        $resolution = (string) ($request->resolution_type ?: ($data['resolution_type'] ?? ''));
        if (! in_array($resolution, ['full_refund', 'partial_refund', 'exchange', 'store_credit'], true)) {
            throw new InvalidArgumentException('Select a valid return resolution.');
        }
        $isRefund = in_array($resolution, ['full_refund', 'partial_refund'], true);
        $amount = $resolution === 'full_refund'
            ? (int) $order->total_minor
            : (isset($data['refund_amount']) && $data['refund_amount'] !== ''
                ? $this->minor($data['refund_amount'], (string) $order->currency)
                : (int) ($request->refund_amount_minor ?? 0));
        if ($isRefund && ($amount <= 0 || $amount > (int) $order->total_minor)) {
            throw new InvalidArgumentException('Refund amount must be greater than zero and cannot exceed the order total.');
        }
        $message = $isRefund
            ? 'The return was accepted. Submit your refund receiving details to continue.'
            : 'The selected return resolution was completed.';
        $this->changeStatus($request, $isRefund ? 'refund_details_required' : 'closed', $actor, $message, [
            'resolution_type' => $resolution,
            'refund_amount_minor' => $isRefund ? $amount : null,
            'refund_method' => null,
            'refunded_at' => null,
            'closed_at' => $isRefund ? null : now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function recordRefundSent(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['refund_details_submitted']);
        $reference = trim((string) ($data['refund_transfer_reference'] ?? ''));
        if ($reference === '') {
            throw new InvalidArgumentException('A bank or CliQ transfer reference is required.');
        }
        $method = (string) $request->refund_destination_method;
        $label = $method === 'cliq' ? 'CliQ' : 'Bank Transfer';
        $this->changeStatus($request, 'refund_sent', $actor, "The refund was sent by {$label}.", [
            'refund_method' => $label,
            'refund_transfer_reference' => $reference,
            'refund_transfer_note' => $this->nullable($data['refund_transfer_note'] ?? null),
            'refund_sent_at' => now(),
            'refunded_at' => now(),
        ], [
            'refund_destination_method' => $method,
            'refund_transfer_reference' => $reference,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function close(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['refund_received']);
        $this->changeStatus($request, 'closed', $actor, $this->nullable($data['message'] ?? null) ?? 'The return case was closed.', ['closed_at' => now()]);
    }

    /** @param array<string, mixed> $data */
    private function expire(object $request, array $data, ActorContext $actor): void
    {
        $this->assertStatus($request, ['approved']);
        $message = $this->nullable($data['reason'] ?? null) ?? 'The return authorization expired before shipment.';
        $this->changeStatus($request, 'expired', $actor, $message, ['admin_response' => $message, 'closed_at' => now()]);
    }

    /** @param array<string, mixed> $updates @param array<string, mixed> $metadata */
    private function changeStatus(object $request, string $target, ActorContext $actor, string $message, array $updates = [], array $metadata = []): void
    {
        DB::transaction(function () use ($request, $target, $actor, $message, $updates, $metadata): void {
            $current = DB::table('store_return_requests')->where('id', $request->id)->lockForUpdate()->first();
            if ($current === null || (string) $current->status !== (string) $request->status) {
                throw new InvalidArgumentException('The return process changed. Refresh the page and try again.');
            }
            DB::table('store_return_requests')->where('id', $current->id)->update($updates + [
                'status' => $target,
                'updated_by' => is_numeric($actor->id) ? (int) $actor->id : null,
                'updated_at' => now(),
            ]);
            $this->record((int) $current->id, $target, (string) $current->status, $target, $actor, $message, $metadata);

            $order = DB::table('store_orders')->where('id', $current->order_id)->first();
            if ($order !== null) {
                $this->orderEvents->record(
                    (int) $order->id,
                    'return_status_changed',
                    $actor,
                    $message,
                    (string) $order->status,
                    (string) $order->status,
                    ['return_request_public_id' => $current->public_id, 'return_status' => $target] + $metadata,
                    'customer',
                );
            }
        });
    }

    /** @param array<string, mixed> $metadata */
    private function record(int $requestId, string $eventType, ?string $from, ?string $to, ActorContext $actor, ?string $message, array $metadata = []): void
    {
        DB::table('store_return_request_events')->insert([
            'return_request_id' => $requestId,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'message' => $message,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'visible_to_customer' => true,
            'created_at' => now(),
        ]);
    }

    private function storeAttachment(object $request, UploadedFile $file, string $kind, string $uploaderType, string $uploaderId): object
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = (string) Str::ulid().'.'.$extension;
        $directory = 'store/returns/'.$request->public_id;
        $path = Storage::disk('local')->putFileAs($directory, $file, $filename);
        if ($path === false) {
            throw new InvalidArgumentException('The return attachment could not be stored.');
        }
        $publicId = (string) Str::ulid();
        $id = DB::table('store_return_request_attachments')->insertGetId([
            'public_id' => $publicId,
            'return_request_id' => $request->id,
            'uploader_type' => $uploaderType,
            'uploader_id' => $uploaderId,
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('store_return_request_attachments')->where('id', $id)->first();
    }

    private function attachmentQuery()
    {
        return DB::table('store_return_request_attachments as attachment')
            ->join('store_return_requests as request', 'request.id', '=', 'attachment.return_request_id')
            ->join('store_orders as orders', 'orders.id', '=', 'request.order_id')
            ->select('attachment.*');
    }

    /** @param list<string> $allowed */
    private function assertStatus(?object $request, array $allowed): void
    {
        if ($request === null || ! in_array((string) $request->status, $allowed, true)) {
            throw new InvalidArgumentException('This action is not available at the current return stage.');
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function validatedRefundDestination(string $method, array $data): array
    {
        $beneficiary = trim((string) ($data['beneficiary_name'] ?? ''));
        if ($beneficiary === '') {
            throw new InvalidArgumentException('The beneficiary name is required.');
        }

        if ($method === 'bank_transfer') {
            $bank = trim((string) ($data['bank_name'] ?? ''));
            $iban = strtoupper(str_replace(' ', '', trim((string) ($data['iban'] ?? ''))));
            if ($bank === '' || $iban === '') {
                throw new InvalidArgumentException('Bank name and IBAN are required for a bank transfer.');
            }

            return array_filter([
                'beneficiary_name' => $beneficiary,
                'bank_name' => $bank,
                'iban' => $iban,
                'account_number' => trim((string) ($data['account_number'] ?? '')),
            ], static fn (string $value): bool => $value !== '');
        }

        $alias = trim((string) ($data['cliq_alias'] ?? ''));
        $phone = trim((string) ($data['cliq_phone'] ?? ''));
        if ($alias === '' && $phone === '') {
            throw new InvalidArgumentException('Enter a CliQ alias or the registered phone number.');
        }

        return array_filter([
            'beneficiary_name' => $beneficiary,
            'cliq_alias' => $alias,
            'cliq_phone' => $phone,
        ], static fn (string $value): bool => $value !== '');
    }

    private function minor(mixed $amount, string $currency): int
    {
        $digits = strtoupper($currency) === 'JOD' ? 3 : 2;

        return (int) round(((float) $amount) * (10 ** $digits));
    }
}
