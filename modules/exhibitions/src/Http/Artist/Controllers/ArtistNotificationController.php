<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;

final class ArtistNotificationController extends Controller
{
    public function __construct(private readonly ArtistDashboardService $dashboard, private readonly ExhibitionNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $id = (int) $request->user()->id;
        $context = $this->dashboard->overview($id)['context'];

        return view('exhibitions::artist.notifications.index', ['notifications' => DB::table('exhibition_notification_deliveries')->where('recipient_user_id', $id)->where('channel', 'database')->latest('id')->paginate(30), 'context' => $context, 'navCounts' => $this->dashboard->navigationCounts($id), 'activeTab' => 'notifications']);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notifications->markRead((int) $request->user()->id, $notification);
        $link = DB::table('exhibition_notification_deliveries')->where('public_id', $notification)->where('recipient_user_id', $request->user()->id)->value('link');

        return $link ? redirect($link) : back();
    }
}
