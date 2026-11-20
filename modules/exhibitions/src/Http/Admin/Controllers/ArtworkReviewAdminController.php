<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Review\ArtworkReviewService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtworkReviewAdminController
{
    public function __construct(private readonly ArtworkReviewService $service) {}

    public function index(Request $r): View
    {
        $reviewStates = ['under_review', 'changes_requested', 'documents_requested', 'escalated'];
        $requestedState = $r->string('state')->toString();
        $state = in_array($requestedState, ['not_opened', ...$reviewStates], true) ? $requestedState : '';
        $latestRoundJoin = static function ($join): void {
            $join->on('rr.artwork_id', '=', 'a.id')
                ->whereRaw('rr.round_number = (select max(r2.round_number) from exhibition_artwork_review_rounds r2 where r2.artwork_id = a.id)');
        };
        $activeQueue = static function ($query) use ($reviewStates): void {
            $query->whereIn('a.state', ['submitted', 'under_review', 'changes_required'])
                ->where(function ($nested) use ($reviewStates): void {
                    $nested->whereIn('rr.state', $reviewStates)
                        ->orWhere(function ($newSubmission): void {
                            $newSubmission->whereNull('rr.id')->where('a.state', 'submitted');
                        });
                });
        };

        $query = DB::table('exhibition_artworks as a')
            ->leftJoin('exhibition_artwork_review_rounds as rr', $latestRoundJoin)
            ->select('a.public_id', 'a.title', 'a.state as artwork_state', 'rr.state as review_state', 'rr.round_number', 'rr.assigned_to');
        $activeQueue($query);
        if ($state === 'not_opened') {
            $query->whereNull('rr.id')->where('a.state', 'submitted');
        } elseif ($state !== '') {
            $query->where('rr.state', $state);
        }

        $countQuery = DB::table('exhibition_artworks as a')
            ->leftJoin('exhibition_artwork_review_rounds as rr', $latestRoundJoin);
        $activeQueue($countQuery);
        $counts = $countQuery
            ->selectRaw("case when rr.id is null then 'not_opened' else rr.state end as queue_state, count(*) as total")
            ->groupBy('queue_state')
            ->pluck('total', 'queue_state');

        return view('exhibitions::admin.artworks.review-index', [
            'rows' => $query->orderByDesc('a.updated_at')->paginate(30)->withQueryString(),
            'state' => $state,
            'counts' => $counts,
        ]);
    }

    public function show(string $artwork): View
    {
        $data = $this->service->reviewerWorkspace($artwork);
        if (! $data['currentRound']) {
            $data['currentRound'] = $this->service->openRound((int) request()->user()->id, $artwork);
        }

        return view('exhibitions::admin.artworks.review-show', $this->service->reviewerWorkspace($artwork) + [
            'decisions' => $this->service->decisions(),
            'overallDecisions' => $this->service->overallDecisions(),
        ]);
    }

    public function displayMedia(string $artwork, string $media): StreamedResponse
    {
        $row = $this->reviewMedia($artwork, $media);
        abort_unless($row->state === 'ready' && $row->display_path, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->display_path), 404);

        return Storage::disk($row->disk)->response($row->display_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadMedia(string $artwork, string $media): StreamedResponse
    {
        $row = $this->reviewMedia($artwork, $media);
        abort_unless($row->state === 'ready' && $row->original_path, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->original_path), 404);

        return Storage::disk($row->disk)->download($row->original_path, $row->original_name, PrivateResponseHeaders::for($row->mime_type ?? 'application/octet-stream'));
    }

    private function reviewMedia(string $artwork, string $media): object
    {
        return DB::table('exhibition_artwork_media as media')
            ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'media.artwork_id')
            ->where('artwork.public_id', $artwork)
            ->where('media.public_id', $media)
            ->select('media.*')
            ->first() ?? abort(404);
    }

    public function open(Request $r, string $artwork): RedirectResponse
    {
        $this->service->openRound((int) $r->user()->id, $artwork);

        return back()->with('status', 'Review round opened idempotently.');
    }

    public function decide(Request $r, string $artwork, string $round, string $section): RedirectResponse
    {
        $v = $r->validate(['decision' => ['required', Rule::in($this->service->decisions())], 'reason' => ['required', 'string', 'max:8000'], 'adjustment_summary' => ['nullable', 'string', 'max:8000'], 'lock_version' => ['required', 'integer', 'min:1']]);
        try {
            $this->service->decide((int) $r->user()->id, $artwork, $round, $section, $v['decision'], $v['reason'], $v['adjustment_summary'] ?? null, (int) $v['lock_version']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'Section decision recorded and overall state recalculated.');
    }

    public function decideOverall(Request $r, string $artwork, string $round): RedirectResponse
    {
        $v = $r->validate([
            'decision' => ['required', Rule::in($this->service->overallDecisions())],
            'reason' => ['nullable', 'string', 'max:8000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->service->decideOverall(
                (int) $r->user()->id,
                $artwork,
                $round,
                $v['decision'],
                $v['reason'] ?? null,
                (int) $v['lock_version'],
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('status', 'The decision was recorded for the complete artwork.');
    }

    public function comment(Request $r, string $artwork, string $round, string $section): RedirectResponse
    {
        $v = $r->validate(['target_type' => ['required', Rule::in(['field', 'media', 'evidence', 'general'])], 'target_reference' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:8000']]);
        try {
            $this->service->comment((int) $r->user()->id, $artwork, $round, $section, $v['target_type'], $v['target_reference'], $v['body']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['comment' => $e->getMessage()]);
        }

        return back()->with('status', 'Actionable review comment attached to its target.');
    }
}
