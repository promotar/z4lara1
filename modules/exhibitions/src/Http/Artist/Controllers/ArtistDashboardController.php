<?php

namespace Modules\Exhibition\Http\Artist\Controllers;

use App\Platform\Core\Localization\PlatformLocale;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardService;
use Modules\Exhibition\Application\Support\ArtistSupportService;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;

final class ArtistDashboardController extends Controller
{
    public function __construct(
        private readonly ArtistDashboardService $dashboard,
        private readonly ArtistDashboardAccess $access,
        private readonly ArtistSupportService $support,
        private readonly AuditRecorder $audit,
    ) {}

    public function overview(Request $request): View
    {
        $coreUserId = (int) $request->user()->id;
        if (! $this->access->canAccess($coreUserId)) {
            abort_unless($request->user()->hasRole('artist'), 403);

            $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $coreUserId)->first();
            $application = DB::table('exhibition_artist_applications')->where('core_user_id', $coreUserId)->first();
            abort_if($identity === null || $identity->state !== 'candidate' || $application === null, 403);

            return view('exhibitions::artist.dashboard.onboarding', [
                'identity' => $identity,
                'application' => $application,
                'history' => DB::table('exhibition_application_status_history')
                    ->where('application_id', $application->id)
                    ->where('to_state', '!=', 'draft')
                    ->orderBy('occurred_at')
                    ->get(),
            ]);
        }

        return view('exhibitions::artist.dashboard.overview', $this->dashboard->overview((int) $request->user()->id) + $this->navigation((int) $request->user()->id, 'overview'));
    }

    public function artworks(Request $request): View
    {
        $validated = $request->validate(['status' => ['nullable', Rule::in($this->dashboard->artworkFilters())]]);
        $filter = $validated['status'] ?? 'all';

        return view('exhibitions::artist.dashboard.artworks', $this->dashboard->artworks((int) $request->user()->id, $filter) + $this->navigation((int) $request->user()->id, 'artworks'));
    }

    public function sales(Request $request): View
    {
        return view('exhibitions::artist.dashboard.sales', $this->dashboard->emptyOperationalSection((int) $request->user()->id, 'sales_and_fulfillment') + $this->navigation((int) $request->user()->id, 'sales'));
    }

    public function earnings(Request $request): View
    {
        return view('exhibitions::artist.dashboard.earnings', $this->dashboard->emptyOperationalSection((int) $request->user()->id, 'earnings_and_settlements') + $this->navigation((int) $request->user()->id, 'earnings'));
    }

    public function profile(Request $request): View
    {
        return view('exhibitions::artist.dashboard.profile', $this->dashboard->profile((int) $request->user()->id) + $this->navigation((int) $request->user()->id, 'profile'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $context = $this->access->context((int) $request->user()->id);
        $request->merge([
            'previous_work_links' => $this->normalizedLinks($request->input('previous_work_links_text')),
            'social_accounts' => $this->normalizedLinks($request->input('social_accounts_text')),
        ]);
        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'artistic_name' => ['required', 'string', 'max:160'],
            'contact_email' => ['required', 'email:rfc', 'max:254'],
            'contact_phone' => ['required', 'string', 'regex:/^[+0-9() .-]{7,40}$/'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'biography' => ['required', 'string', 'min:20', 'max:5000'],
            'years_experience' => ['nullable', 'integer', 'between:0,100'],
            'qualifications' => ['nullable', 'string', 'max:4000'],
            'previous_exhibitions' => ['nullable', 'string', 'max:4000'],
            'awards_certificates' => ['nullable', 'string', 'max:4000'],
            'previous_work_links' => ['array', 'max:10'],
            'previous_work_links.*' => ['url:http,https', 'max:2048'],
            'social_accounts' => ['array', 'max:10'],
            'social_accounts.*' => ['url:http,https', 'max:2048'],
        ]);

        $user = $request->user();
        $applicationFields = [
            'artistic_name', 'contact_email', 'contact_phone', 'city', 'country', 'biography',
            'years_experience', 'qualifications', 'previous_exhibitions', 'awards_certificates',
        ];
        $emailChanged = strcasecmp((string) $user->email, $validated['email']) !== 0;
        $before = [
            'account_name' => (string) $user->name,
            'login_email' => (string) $user->email,
        ] + array_intersect_key((array) $context, array_flip($applicationFields));
        $applicationValues = array_intersect_key($validated, array_flip($applicationFields));
        $applicationValues['previous_work_links'] = json_encode($validated['previous_work_links'], JSON_THROW_ON_ERROR);
        $applicationValues['social_accounts'] = json_encode($validated['social_accounts'], JSON_THROW_ON_ERROR);
        $auditedValues = [
            'account_name' => $validated['name'],
            'login_email' => $validated['email'],
        ] + $applicationValues;

        DB::transaction(function () use ($user, $validated, $context, $applicationValues, $emailChanged): void {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->save();
            DB::table('exhibition_artist_applications')->where('id', $context->id)->update($applicationValues + [
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
        });

        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.artist_profile.updated',
            targetType: 'artist_application',
            targetId: (string) $context->public_id,
            previousValues: $before,
            newValues: $auditedValues,
            reason: 'Artist updated profile information from Profile & Documents.',
            actorUserId: (int) $user->id,
        ));

        if ($emailChanged) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $exception) {
                report($exception);
            }

            return back()->with('profile_status', 'Profile information updated. Please verify your new login email address.');
        }

        return back()->with('profile_status', 'Profile information updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->access->context((int) $request->user()->id);
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);
        $request->user()->update(['password' => Hash::make($validated['password'])]);
        $this->audit->record(new AuditEntryData(
            action: 'exhibitions.artist_account.password_updated',
            targetType: 'core_user',
            targetId: (string) $request->user()->id,
            reason: 'Artist changed the account password from Profile & Documents.',
            actorUserId: (int) $request->user()->id,
        ));

        return back()->with('password_status', 'Password updated successfully.');
    }

    public function support(Request $request): View
    {
        $context = $this->access->context((int) $request->user()->id);

        return view('exhibitions::artist.dashboard.support.index', [
            'context' => $context,
            'tickets' => DB::table('exhibition_artist_support_tickets')->where('artist_identity_id', $context->artist_identity_id)->orderByDesc('last_message_at')->paginate(20),
        ] + $this->navigation((int) $request->user()->id, 'support'));
    }

    public function createTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate(['subject' => ['required', 'string', 'max:180'], 'category' => ['required', Rule::in(['verification', 'artwork', 'fulfillment', 'settlement', 'account', 'other'])], 'message' => ['required', 'string', 'min:10', 'max:5000']]);
        $ticket = $this->support->create((int) $request->user()->id, $validated['subject'], $validated['category'], $validated['message']);

        return redirect()->route('exhibitions.dashboard.support.show', $ticket->public_id)->with('status', 'Support ticket created for the platform team.');
    }

    public function showTicket(Request $request, string $ticket): View
    {
        $row = $this->support->ownedTicket((int) $request->user()->id, $ticket);

        return view('exhibitions::artist.dashboard.support.show', [
            'context' => $this->access->context((int) $request->user()->id), 'ticket' => $row,
            'messages' => DB::table('exhibition_artist_support_messages')->where('ticket_id', $row->id)->orderBy('created_at')->get(),
        ] + $this->navigation((int) $request->user()->id, 'support'));
    }

    public function addMessage(Request $request, string $ticket): RedirectResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        try {
            $this->support->addArtistMessage((int) $request->user()->id, $ticket, $validated['message']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['message' => $exception->getMessage()]);
        }

        return back()->with('status', 'Message sent to the platform team.');
    }

    /** @return array<string,mixed> */
    private function navigation(int $coreUserId, string $active): array
    {
        return ['navCounts' => $this->dashboard->navigationCounts($coreUserId), 'activeTab' => $active];
    }

    /** @return list<string> */
    private function normalizedLinks(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R+/', $value) ?: [])));
    }
}
