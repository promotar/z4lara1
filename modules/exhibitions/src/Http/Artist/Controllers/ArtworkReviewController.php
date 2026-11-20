<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Review\ArtworkReviewService;

final class ArtworkReviewController
{
    public function __construct(private readonly ArtworkReviewService $service, private readonly ArtistDashboardService $dashboard) {}

    public function show(Request $r, string $artwork): View
    {
        return view('exhibitions::artist.artworks.review', $this->service->ownerWorkspace((int) $r->user()->id, $artwork) + $this->nav((int) $r->user()->id));
    }

    public function reply(Request $r, string $artwork, string $comment): RedirectResponse
    {
        $v = $r->validate(['body' => ['required', 'string', 'max:8000']]);
        try {
            $this->service->reply((int) $r->user()->id, $artwork, $comment, $v['body']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back()->with('status', 'Response added without altering the reviewer decision.');
    }

    public function resubmit(Request $r, string $artwork): RedirectResponse
    {
        $v = $r->validate(['reason' => ['required', 'string', 'max:8000']]);
        try {
            $this->service->resubmit((int) $r->user()->id, $artwork, $v['reason']);
        } catch (DomainException$e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'A new review round was created; all earlier rounds remain available.');
    }

    private function nav(int $user): array
    {
        return ['context' => $this->dashboard->overview($user)['context'], 'navCounts' => $this->dashboard->navigationCounts($user), 'activeTab' => 'artworks'];
    }
}
