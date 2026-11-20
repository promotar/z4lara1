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
use Modules\Exhibition\Application\Artists\ArtistAccountService;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Modules\Exhibition\Application\Verification\ActivationEligibility;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;
use Modules\Exhibition\Domain\Artists\ArtistAccountState;
use Modules\Exhibition\Domain\Verification\VerificationState;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VerificationAdminController extends Controller
{
    public function __construct(
        private readonly ArtistVerificationService $verification,
        private readonly ActivationEligibility $eligibility,
        private readonly ArtistAccountService $accounts,
        private readonly CoreUserGateway $users,
    ) {}

    public function show(string $application): View
    {
        $applicationRow = $this->application($application);
        $case = $this->verification->forApplication($applicationRow);

        return view('exhibitions::admin.verification.show', [
            'application' => $applicationRow, 'case' => $case,
            'applicant' => $this->users->find((int) $applicationRow->core_user_id) ?? abort(404),
            'documents' => DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->orderBy('type')->orderByDesc('version')->get(),
            'agreement' => DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first(),
            'payout' => DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first(),
            'checklist' => $this->eligibility->checklist((int) $applicationRow->id),
            'sellerSetup' => $this->eligibility->progress((int) $applicationRow->id),
            'identity' => DB::table('exhibition_artist_identities')->where('core_user_id', $applicationRow->core_user_id)->first(),
        ]);
    }

    public function decide(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', Rule::enum(VerificationState::class)], 'reason' => ['required', 'string', 'max:4000'],
            'instructions' => ['nullable', 'string', 'max:4000'], 'response_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'contact_verified' => ['nullable', 'boolean'], 'profile_verified' => ['nullable', 'boolean'], 'samples_verified' => ['nullable', 'boolean'],
        ]);
        $row = $this->application($application);
        $case = $this->verification->forApplication($row);
        try {
            $this->verification->decide($case, VerificationState::from($validated['state']), [
                'contact' => (bool) ($validated['contact_verified'] ?? false),
                'profile' => (bool) ($validated['profile_verified'] ?? false),
                'samples' => (bool) ($validated['samples_verified'] ?? false),
            ], (int) $request->user()->id, $validated['reason'], $validated['instructions'] ?? null, $validated['response_deadline'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['state' => $exception->getMessage()]);
        }

        return back()->with('status', 'Verification decision recorded.');
    }

    public function publishTemplate(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', 'unique:exhibition_agreement_templates,version'],
            'title' => ['required', 'string', 'max:180'], 'content' => ['required', 'string', 'min:100', 'max:50000'],
        ]);
        $template = $this->verification->publishTemplate(
            $validated['version'], $validated['title'], $validated['content'], 0, [],
            (int) $request->user()->id,
        );
        $row = $this->application($application);
        $case = $this->verification->forApplication($row);
        $this->verification->assignAgreement($case, $template, (int) $request->user()->id);

        return back()->with('status', 'Versioned agreement published and assigned.');
    }

    public function assignAgreement(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate(['template' => ['required', 'uuid', 'exists:exhibition_agreement_templates,public_id']]);
        $row = $this->application($application);
        $case = $this->verification->forApplication($row);
        $template = DB::table('exhibition_agreement_templates')->where('public_id', $validated['template'])->first();
        try {
            $this->verification->assignAgreement($case, $template, (int) $request->user()->id);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['template' => $exception->getMessage()]);
        }

        return back()->with('status', 'Agreement version assigned.');
    }

    public function verifyPayout(Request $request, string $application): RedirectResponse
    {
        $row = $this->application($application);
        $case = $this->verification->forApplication($row);
        try {
            $this->verification->verifyPayout($case, (int) $request->user()->id);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payout' => $exception->getMessage()]);
        }

        return back()->with('status', 'Payout details verified.');
    }

    public function changeAccountState(Request $request, string $application): RedirectResponse
    {
        $validated = $request->validate(['state' => ['required', Rule::enum(ArtistAccountState::class)], 'reason' => ['required', 'string', 'max:4000']]);
        $row = $this->application($application);
        try {
            $this->accounts->transition((int) $row->core_user_id, ArtistAccountState::from($validated['state']), (int) $request->user()->id, $validated['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['account_state' => $exception->getMessage()]);
        }

        return back()->with('status', 'Artist account state updated with an audit reason.');
    }

    public function downloadDocument(string $application, string $document): StreamedResponse
    {
        $row = DB::table('exhibition_verification_documents as documents')
            ->join('exhibition_verification_cases as cases', 'cases.id', '=', 'documents.verification_case_id')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'cases.application_id')
            ->where('applications.public_id', $application)->where('documents.public_id', $document)->select('documents.*')->first();
        abort_if($row === null, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function application(string $publicId): object
    {
        return DB::table('exhibition_artist_applications')->where('public_id', $publicId)->first() ?? abort(404);
    }
}
