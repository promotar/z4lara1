<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;

final class CommunicationAdminController extends Controller
{
    public function __construct(private readonly ExhibitionNotificationService $notifications) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);

        return view('exhibitions::admin.communications.index', ['catalog' => $this->notifications->catalog(), 'templates' => DB::table('exhibition_notification_templates')->orderBy('event_key')->get()->keyBy(fn ($r) => $r->event_key.'|'.$r->channel.'|'.$r->locale), 'deliveries' => DB::table('exhibition_notification_deliveries')->latest('id')->paginate(50), 'locale' => app()->getLocale()]);
    }

    public function template(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $v = $request->validate(['event' => ['required', 'string'], 'channel' => ['required', 'in:database,email'], 'locale' => ['required', 'string', 'max:12'], 'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000'], 'reason' => ['required', 'string', 'min:8', 'max:1000']]);
        try {
            $this->notifications->updateTemplate($request->user()->id, $v['event'], $v['channel'], $v['locale'], $v['subject'], $v['body'], $v['reason']);
        } catch (DomainException$e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return back()->with('status', 'Notification template saved as a new version.');
    }

    public function retry(Request $request, string $delivery): RedirectResponse
    {
        abort_unless($request->user()->can('exhibitions.manage'), 403);
        $this->notifications->attempt($delivery);

        return back()->with('status', 'Delivery retry processed and logged.');
    }
}
