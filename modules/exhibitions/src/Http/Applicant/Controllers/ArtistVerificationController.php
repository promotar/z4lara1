<?php

namespace Modules\Exhibition\Http\Applicant\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Modules\Exhibition\Application\Verification\ActivationEligibility;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtistVerificationController extends Controller
{
    public function __construct(
        private readonly ArtistVerificationService $verification,
        private readonly ActivationEligibility $eligibility,
    ) {}

    public function show(Request $request): RedirectResponse
    {
        $application = $this->applicationForUser((int) $request->user()->id);
        if (! in_array($application->state, ['approved', 'contract_pending', 'active'], true)) {
            return redirect()->route('exhibitions.application.status');
        }

        $case = $this->verification->forApplication($application);
        $agreementAccepted = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->whereNotNull('accepted_at')->exists();
        if ($case->state === 'verified' && $agreementAccepted) {
            return redirect()->route('exhibitions.verification.payout');
        }

        return redirect()->route('exhibitions.verification.identity');
    }

    public function identity(Request $request): View|RedirectResponse
    {
        return $this->renderStep($request, 'identity');
    }

    public function agreement(Request $request): View|RedirectResponse
    {
        return $this->renderStep($request, 'agreement');
    }

    public function payout(Request $request): View|RedirectResponse
    {
        return $this->renderStep($request, 'payout');
    }

    private function renderStep(Request $request, string $step): View|RedirectResponse
    {
        $application = $this->applicationForUser((int) $request->user()->id);
        if (! in_array($application->state, ['approved', 'contract_pending', 'active'], true)) {
            return redirect()->route('exhibitions.application.status');
        }
        $case = $this->verification->forApplication($application);
        $documents = DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->orderBy('type')->orderByDesc('version')->get();
        $documents->each(function (object $document): void {
            $document->download_url = URL::temporarySignedRoute('exhibitions.verification.documents.download', now()->addMinutes(10), ['document' => $document->public_id]);
        });
        $currentIdentity = $documents->first(fn (object $document): bool => str_starts_with($document->type, 'identity') && $document->status === 'current');
        $agreement = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first();
        $payout = DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first();

        if ($step === 'agreement' && $currentIdentity === null) {
            return redirect()->route('exhibitions.verification.identity')->with('warning', 'Upload your identity document before continuing to the agreement.');
        }
        if ($step === 'payout' && $currentIdentity === null) {
            return redirect()->route('exhibitions.verification.identity')->with('warning', 'Upload your identity document before continuing to payout information.');
        }
        if ($step === 'payout' && $agreement?->accepted_at === null) {
            return redirect()->route('exhibitions.verification.agreement')->with('warning', 'Accept the artist agreement before continuing to payout information.');
        }

        $identity = DB::table('exhibition_artist_identities')->where('core_user_id', $application->core_user_id)->first();

        $accountState = ArtistAccountState::tryFrom((string) ($identity?->state ?? ''));

        return view('exhibitions::applicant.verification', [
            'application' => $application, 'case' => $case, 'documents' => $documents,
            'agreement' => $agreement,
            'payout' => $payout,
            'checklist' => $this->eligibility->checklist((int) $application->id),
            'sellerSetup' => $this->eligibility->progress((int) $application->id),
            'accountState' => $accountState,
            'accountCapabilities' => $accountState?->capabilities(),
            'step' => $step,
        ]);
    }

    public function uploadDocuments(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['identity', 'additional'])],
            'documents' => ['required', 'array', 'between:1,4'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('exhibitions.verification.document_max_kilobytes', 10240)],
        ]);
        $application = $this->applicationForUser((int) $request->user()->id);
        $case = $this->verification->forApplication($application);
        $this->verification->storeDocuments($case, $validated['type'], (array) $request->file('documents'), (int) $request->user()->id);

        $nextRoute = $validated['type'] === 'identity'
            ? 'exhibitions.verification.agreement'
            : 'exhibitions.verification.identity';

        return redirect()->route($nextRoute)->with('status', 'Private verification document uploaded. Previous versions remain in history.');
    }

    public function savePayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(['bank_transfer', 'iban', 'wallet', 'cliq', 'check', 'cash'])],
            'account_name' => ['required', 'string', 'max:160'],
            'account_identifier' => [Rule::requiredIf(fn (): bool => in_array($request->input('method'), ['bank_transfer', 'iban', 'wallet', 'cliq'], true)), 'nullable', 'string', 'min:3', 'max:120'],
            'bank_or_provider' => [Rule::requiredIf(fn (): bool => in_array($request->input('method'), ['bank_transfer', 'iban', 'wallet'], true)), 'nullable', 'string', 'max:160'],
        ]);
        $application = $this->applicationForUser((int) $request->user()->id);
        $case = $this->verification->forApplication($application);
        if (! $this->hasCurrentIdentity((int) $case->id)) {
            return redirect()->route('exhibitions.verification.identity')->with('warning', 'Upload your identity document before continuing to payout information.');
        }
        $agreement = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first();
        if ($agreement?->accepted_at === null) {
            return redirect()->route('exhibitions.verification.agreement')->with('warning', 'Accept the artist agreement before continuing to payout information.');
        }
        $this->verification->savePayout($case, $validated['method'], $validated, (int) $request->user()->id);

        return redirect()->route('exhibitions.application.status')->with('status', 'Payout details stored securely and sent for verification.');
    }

    public function acceptAgreement(Request $request): RedirectResponse
    {
        $request->validate(['accept_agreement' => ['accepted']]);
        $application = $this->applicationForUser((int) $request->user()->id);
        $case = $this->verification->forApplication($application);
        if (! $this->hasCurrentIdentity((int) $case->id)) {
            return redirect()->route('exhibitions.verification.identity')->with('warning', 'Upload your identity document before accepting the artist agreement.');
        }
        try {
            $this->verification->acceptAgreement($case, (int) $request->user()->id, [
                'ip' => hash('sha256', (string) $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'application_public_id' => (string) $application->public_id,
            ]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['agreement' => $exception->getMessage()]);
        }

        return redirect()->route('exhibitions.verification.payout')->with('status', 'Agreement accepted. The exact presented version is now immutable.');
    }

    public function downloadDocument(Request $request, string $document): StreamedResponse
    {
        $row = DB::table('exhibition_verification_documents as documents')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'documents.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('documents.public_id', $document)->select('documents.*', 'applications.core_user_id')->first();
        abort_if($row === null, 404);
        abort_unless((int) $row->core_user_id === (int) $request->user()->id, 403);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function applicationForUser(int $userId): object
    {
        return DB::table('exhibition_artist_applications')->where('core_user_id', $userId)->first() ?? abort(404);
    }

    private function hasCurrentIdentity(int $caseId): bool
    {
        return DB::table('exhibition_verification_documents')
            ->where('verification_case_id', $caseId)
            ->where('type', 'like', 'identity%')
            ->where('status', 'current')
            ->exists();
    }
}
