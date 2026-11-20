<?php

namespace Modules\Exhibition\Http\Admin\Controllers;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;
use Modules\Exhibition\Domain\Applications\ApplicationState;
use Modules\Exhibition\Domain\Verification\VerificationState;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ApplicationAdminController extends Controller
{
    public function __construct(
        private readonly ArtistApplicationService $applications,
        private readonly ArtistVerificationService $verification,
        private readonly CoreUserGateway $users,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'state' => ['nullable', Rule::enum(ApplicationState::class)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $query = DB::table('exhibition_artist_applications as applications')
            ->leftJoin('exhibition_artist_identities as identities', 'identities.core_user_id', '=', 'applications.core_user_id')
            ->select('applications.*', 'identities.state as artist_account_state');
        if (! empty($validated['state'])) {
            $query->where('applications.state', $validated['state']);
        }
        if (! empty($validated['search'])) {
            $search = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(function ($nested) use ($search): void {
                $nested->where('applications.artistic_name', 'like', $search)
                    ->orWhere('applications.legal_name', 'like', $search)
                    ->orWhere('applications.contact_email', 'like', $search)
                    ->orWhere('applications.public_id', 'like', $search);
            });
        }

        $counts = DB::table('exhibition_artist_applications')->select('state', DB::raw('COUNT(*) as aggregate'))->groupBy('state')->pluck('aggregate', 'state');
        $applications = $query->orderByDesc('applications.updated_at')->paginate(25)->withQueryString();
        $applications->getCollection()->transform(function (object $application): object {
            $user = $this->users->find((int) $application->core_user_id);
            $application->applicant_name = $user?->name ?? $application->legal_name ?? 'Unknown applicant';
            $application->applicant_account_email = $user?->email ?? $application->contact_email ?? '';

            return $application;
        });

        return view('exhibitions::admin.applications.index', [
            'applications' => $applications,
            'counts' => $counts,
            'states' => ApplicationState::cases(),
            'filters' => $validated,
        ]);
    }

    public function show(string $application): View
    {
        $row = DB::table('exhibition_artist_applications')->where('public_id', $application)->first();
        abort_if($row === null, 404);

        $case = $this->verification->forApplication($row);

        return view('exhibitions::admin.applications.show', [
            'application' => $row,
            'applicant' => $this->users->find((int) $row->core_user_id) ?? abort(404),
            'history' => DB::table('exhibition_application_status_history')
                ->where('application_id', $row->id)
                ->where('to_state', '!=', ApplicationState::Draft->value)
                ->orderBy('occurred_at')
                ->get(),
            'samples' => DB::table('exhibition_application_samples')->where('application_id', $row->id)->orderBy('id')->get(),
            'supportingDocuments' => DB::table('exhibition_application_supporting_documents')->where('application_id', $row->id)->orderBy('id')->get(),
            'identityDocuments' => DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->where('type', 'like', 'identity%')->where('status', 'current')->orderBy('type')->get(),
            'agreement' => DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first(),
            'identity' => DB::table('exhibition_artist_identities')->where('core_user_id', $row->core_user_id)->first(),
            'accountHistory' => DB::table('exhibition_artist_account_status_history as history')
                ->join('exhibition_artist_identities as identities', 'identities.id', '=', 'history.artist_identity_id')
                ->where('identities.core_user_id', $row->core_user_id)
                ->select('history.*')
                ->orderByDesc('history.occurred_at')
                ->get()
                ->each(function (object $history): void {
                    $history->actor_name = $history->actor_user_id === null
                        ? null
                        : $this->users->find((int) $history->actor_user_id)?->name;
                }),
            'states' => [
                ApplicationState::Approved,
                ApplicationState::Rejected,
                ApplicationState::DocumentsRequired,
            ],
        ]);
    }

    public function transition(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', Rule::in([
                ApplicationState::Approved->value,
                ApplicationState::Rejected->value,
                ApplicationState::DocumentsRequired->value,
            ])],
            'reason' => ['nullable', 'required_if:state,rejected', 'string', 'max:4000'],
            'next_action' => ['nullable', 'required_if:state,documents_required', 'string', 'max:4000'],
            'response_deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        try {
            DB::transaction(function () use ($application, $request, $validated): void {
                $row = DB::table('exhibition_artist_applications')->where('public_id', $application)->first() ?? abort(404);
                if ($validated['state'] === ApplicationState::Approved->value) {
                    $case = $this->verification->forApplication($row);
                    $hasIdentity = DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->where('type', 'like', 'identity%')->where('status', 'current')->exists();
                    $agreementAccepted = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->whereNotNull('accepted_at')->exists();
                    if (! $hasIdentity || ! $agreementAccepted) {
                        throw new DomainException('Identity documents and the accepted artist agreement are required before approval.');
                    }
                    $this->verification->decide(
                        $case,
                        VerificationState::Verified,
                        ['contact' => true, 'profile' => true, 'samples' => true],
                        (int) $request->user()->id,
                        'Identity and artist application reviewed and approved together.',
                    );
                }
                $this->applications->transition(
                    $application,
                    ApplicationState::from($validated['state']),
                    (int) $request->user()->id,
                    $validated['reason'] ?? null,
                    $validated['next_action'] ?? null,
                    $validated['response_deadline'] ?? null,
                );
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['state' => $exception->getMessage()]);
        }

        return redirect()->route('admin.plugins.exhibitions.applications.show', $application)->with('status', 'Application state updated.');
    }

    public function downloadSample(string $application, string $sample): StreamedResponse
    {
        $row = $this->findSample($application, $sample);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    public function previewSample(string $application, string $sample): StreamedResponse
    {
        $row = $this->findSample($application, $sample);
        abort_unless(in_array($row->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk($row->disk)->response(
            $row->path,
            $row->original_name,
            PrivateResponseHeaders::for($row->mime_type),
            'inline',
        );
    }

    public function downloadSupportingDocument(string $application, string $document): StreamedResponse
    {
        $row = DB::table('exhibition_application_supporting_documents as documents')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'documents.application_id')
            ->where('applications.public_id', $application)
            ->where('documents.public_id', $document)
            ->select('documents.*')
            ->first();
        abort_if($row === null, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function findSample(string $application, string $sample): object
    {
        $row = DB::table('exhibition_application_samples as samples')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'samples.application_id')
            ->where('applications.public_id', $application)
            ->where('samples.public_id', $sample)
            ->select('samples.*')
            ->first();
        abort_if($row === null, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return $row;
    }
}
