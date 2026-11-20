<?php

namespace Modules\Exhibition\Application\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final readonly class ArtistSupportService
{
    public function __construct(private ArtistDashboardAccess $access, private AuditRecorder $audit) {}

    public function create(int $coreUserId, string $subject, string $category, string $body): object
    {
        $context = $this->access->context($coreUserId);
        $now = now();

        return DB::transaction(function () use ($context, $coreUserId, $subject, $category, $body, $now): object {
            $id = DB::table('exhibition_artist_support_tickets')->insertGetId([
                'public_id' => (string) Str::uuid(), 'artist_identity_id' => $context->artist_identity_id, 'subject' => $subject,
                'category' => $category, 'state' => 'awaiting_platform', 'last_message_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('exhibition_artist_support_messages')->insert([
                'public_id' => (string) Str::uuid(), 'ticket_id' => $id, 'sender_user_id' => $coreUserId, 'sender_role' => 'artist', 'body' => $body, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $ticket = DB::table('exhibition_artist_support_tickets')->where('id', $id)->first();
            $this->audit->record(new AuditEntryData(action: 'exhibitions.support.ticket_created', targetType: 'artist_support_ticket', targetId: (string) $ticket->public_id, newValues: ['category' => $category, 'state' => 'awaiting_platform'], actorUserId: $coreUserId));

            return $ticket;
        });
    }

    public function addArtistMessage(int $coreUserId, string $publicId, string $body): object
    {
        $ticket = $this->ownedTicket($coreUserId, $publicId);
        if ($ticket->state === 'closed') {
            throw new DomainException('A closed support ticket cannot receive new messages.');
        }
        $now = now();
        DB::transaction(function () use ($ticket, $coreUserId, $body, $now): void {
            DB::table('exhibition_artist_support_messages')->insert(['public_id' => (string) Str::uuid(), 'ticket_id' => $ticket->id, 'sender_user_id' => $coreUserId, 'sender_role' => 'artist', 'body' => $body, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('exhibition_artist_support_tickets')->where('id', $ticket->id)->update(['state' => 'awaiting_platform', 'last_message_at' => $now, 'updated_at' => $now]);
        });

        return DB::table('exhibition_artist_support_tickets')->where('id', $ticket->id)->first();
    }

    public function ownedTicket(int $coreUserId, string $publicId): object
    {
        $context = $this->access->context($coreUserId);

        return DB::table('exhibition_artist_support_tickets')->where('artist_identity_id', $context->artist_identity_id)->where('public_id', $publicId)->first() ?? abort(404);
    }
}
