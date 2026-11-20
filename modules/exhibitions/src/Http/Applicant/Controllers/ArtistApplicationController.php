<?php

namespace Modules\Exhibition\Http\Applicant\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Modules\Exhibition\Application\Dashboard\ArtistDashboardAccess;
use Modules\Exhibition\Application\Security\PrivateResponseHeaders;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;
use Modules\Exhibition\Domain\Applications\ApplicationState;
use Modules\Exhibition\Http\Applicant\Support\ArtistApplicationRules;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtistApplicationController extends Controller
{
    public function __construct(
        private readonly ArtistApplicationService $applications,
        private readonly ArtistDashboardAccess $dashboardAccess,
        private readonly ArtistVerificationService $verification,
        private readonly ExhibitionSettingsService $settings,
    ) {}

    public function application(Request $request): View|RedirectResponse
    {
        $application = $this->applications->forUser((int) $request->user()->id);
        $this->assertApplicationsOpenFor((int) $request->user()->id, $application);
        if ($application !== null && ! in_array($application->state, [
            ApplicationState::Draft->value,
            ApplicationState::DocumentsRequired->value,
            ApplicationState::Rejected->value,
        ], true)) {
            return redirect()->route('exhibitions.application.status');
        }

        $case = $application ? $this->verification->forApplication($application) : null;
        $agreement = $case ? DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first() : null;

        $templateQuery = DB::table('exhibition_agreement_templates');
        if (Schema::hasColumn('exhibition_agreement_templates', 'is_active') && Schema::hasColumn('exhibition_agreement_templates', 'is_default')) {
            $templateQuery->where('is_active', true)->where('is_default', true);
        }

        return view('exhibitions::applicant.application', [
            'application' => $application,
            'samples' => $application ? DB::table('exhibition_application_samples')->where('application_id', $application->id)->orderBy('id')->get() : collect(),
            'supportingDocuments' => $application ? DB::table('exhibition_application_supporting_documents')->where('application_id', $application->id)->orderBy('id')->get() : collect(),
            'identityDocuments' => $case ? DB::table('exhibition_verification_documents')->where('verification_case_id', $case->id)->where('type', 'like', 'identity%')->where('status', 'current')->orderBy('type')->get() : collect(),
            'agreement' => $agreement,
            'agreementTemplate' => $agreement ?: $templateQuery->orderByDesc('published_at')->first(),
        ]);
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $this->assertApplicationsOpenFor((int) $request->user()->id);
        $data = $request->validate($this->rules(false));
        try {
            $application = $this->applications->saveDraft((int) $request->user()->id, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['application' => $exception->getMessage()]);
        }
        $this->applications->storeSamples($application, (array) $request->file('sample_artworks', []));
        $this->applications->storeSupportingDocuments($application, (array) $request->file('supporting_documents', []));
        $this->storeIdentityDocuments($request, $application);

        return redirect()->route('exhibitions.application')->with('status', 'Draft saved.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $this->assertApplicationsOpenFor((int) $request->user()->id);
        $data = $request->validate($this->rules(true));
        $existing = $this->applications->forUser((int) $request->user()->id);
        $sampleCount = $existing ? DB::table('exhibition_application_samples')->where('application_id', $existing->id)->count() : 0;
        if ($sampleCount === 0 && count((array) $request->file('sample_artworks', [])) === 0) {
            throw ValidationException::withMessages(['sample_artworks' => 'At least one private sample artwork is required.']);
        }
        $this->assertIdentityDocumentsAvailable($request, $existing);

        try {
            $application = $this->applications->saveDraft((int) $request->user()->id, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['application' => $exception->getMessage()]);
        }
        $this->applications->storeSamples($application, (array) $request->file('sample_artworks', []));
        $this->applications->storeSupportingDocuments($application, (array) $request->file('supporting_documents', []));
        $case = $this->storeIdentityDocuments($request, $application);
        try {
            $this->verification->acceptAgreement($case, (int) $request->user()->id, [
                'ip' => hash('sha256', (string) $request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'application_public_id' => (string) $application->public_id,
                'accepted_during_application' => true,
            ]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['accept_artist_agreement' => $exception->getMessage()]);
        }
        $this->applications->submit((int) $request->user()->id, $data);

        return redirect()->route('exhibitions.application.status')->with('status', 'Application submitted successfully.');
    }

    public function status(Request $request): View|RedirectResponse
    {
        $application = $this->applications->forUser((int) $request->user()->id);
        if ($application === null) {
            return redirect()->route('exhibitions.application');
        }

        return view('exhibitions::applicant.status', [
            'application' => $application,
            'state' => ApplicationState::from($application->state),
            'history' => DB::table('exhibition_application_status_history')
                ->where('application_id', $application->id)
                ->where('to_state', '!=', ApplicationState::Draft->value)
                ->orderBy('occurred_at')
                ->get(),
            'samples' => DB::table('exhibition_application_samples')->where('application_id', $application->id)->orderBy('id')->get(),
            'supportingDocuments' => DB::table('exhibition_application_supporting_documents')->where('application_id', $application->id)->orderBy('id')->get(),
            'dashboardAccessible' => $this->dashboardAccess->canAccess((int) $request->user()->id),
        ]);
    }

    public function downloadSample(Request $request, string $sample): StreamedResponse
    {
        $row = $this->findOwnedSample($request, $sample);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    public function previewSample(Request $request, string $sample): StreamedResponse
    {
        $row = $this->findOwnedSample($request, $sample);
        abort_unless(in_array($row->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk($row->disk)->response(
            $row->path,
            $row->original_name,
            PrivateResponseHeaders::for($row->mime_type),
            'inline',
        );
    }

    public function downloadSupportingDocument(Request $request, string $document): StreamedResponse
    {
        $row = DB::table('exhibition_application_supporting_documents as documents')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'documents.application_id')
            ->where('documents.public_id', $document)
            ->select('documents.*', 'applications.core_user_id')
            ->first();
        abort_if($row === null, 404);
        abort_unless((int) $row->core_user_id === (int) $request->user()->id, 403);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return Storage::disk($row->disk)->download($row->path, $row->original_name, PrivateResponseHeaders::for($row->mime_type));
    }

    private function findOwnedSample(Request $request, string $sample): object
    {
        $row = DB::table('exhibition_application_samples as samples')
            ->join('exhibition_artist_applications as applications', 'applications.id', '=', 'samples.application_id')
            ->where('samples.public_id', $sample)
            ->select('samples.*', 'applications.core_user_id')
            ->first();
        abort_if($row === null, 404);
        abort_unless((int) $row->core_user_id === (int) $request->user()->id, 403);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404);

        return $row;
    }

    private function storeIdentityDocuments(Request $request, object $application): object
    {
        $case = $this->verification->forApplication($application);
        $type = (string) $request->input('identity_document_type');
        $slots = match ($type) {
            'national_id' => ['identity_front' => 'identity_national_front', 'identity_back' => 'identity_national_back'],
            'driver_license' => ['identity_front' => 'identity_license_front', 'identity_back' => 'identity_license_back'],
            'passport' => ['identity_single' => 'identity_passport'],
            default => [],
        };
        foreach ($slots as $input => $documentType) {
            if ($request->hasFile($input)) {
                $this->verification->storeDocuments($case, $documentType, [$request->file($input)], (int) $request->user()->id);
            }
        }

        return $case;
    }

    private function assertIdentityDocumentsAvailable(Request $request, ?object $application): void
    {
        $type = (string) $request->input('identity_document_type');
        $required = match ($type) {
            'national_id' => ['identity_front' => 'identity_national_front', 'identity_back' => 'identity_national_back'],
            'driver_license' => ['identity_front' => 'identity_license_front', 'identity_back' => 'identity_license_back'],
            'passport' => ['identity_single' => 'identity_passport'],
            default => [],
        };
        $caseId = $application ? DB::table('exhibition_verification_cases')->where('application_id', $application->id)->value('id') : null;
        $errors = [];
        foreach ($required as $input => $documentType) {
            $stored = $caseId && DB::table('exhibition_verification_documents')->where('verification_case_id', $caseId)->where('type', $documentType)->where('status', 'current')->exists();
            if (! $stored && ! $request->hasFile($input)) {
                $errors[$input] = $input === 'identity_back' ? 'The back image is required for this identity document.' : 'The required identity document image is missing.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertApplicationsOpenFor(int $userId, ?object $application = null): void
    {
        $application ??= $this->applications->forUser($userId);
        if ($application === null) {
            abort_unless((bool) $this->settings->value('exhibitions.artist_applications.enabled', true), 403, 'New artist applications are currently closed.');
        }
    }

    /** @return array<string, mixed> */
    private function rules(bool $submit): array
    {
        return ArtistApplicationRules::rules($submit);
    }
}
