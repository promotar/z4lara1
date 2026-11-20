<?php

namespace Modules\Exhibition\Http\Applicant\Controllers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Modules\Exhibition\Application\Applications\ArtistApplicationService;
use Modules\Exhibition\Application\Verification\ArtistVerificationService;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use Modules\Exhibition\Domain\Applications\ApplicationState;
use Modules\Exhibition\Http\Applicant\Support\ArtistApplicationRules;
use Modules\Exhibition\Support\CountryCatalog;
use RuntimeException;
use Throwable;

final class ArtistRegistrationController
{
    public function __construct(
        private readonly ArtistApplicationService $applications,
        private readonly ArtistVerificationService $verification,
        private readonly CoreUserGateway $users,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            $application = $this->applications->forUser((int) $request->user()->getAuthIdentifier());

            return $application !== null && $application->state !== ApplicationState::Draft->value
                ? redirect()->route('exhibitions.dashboard.overview')
                : redirect()->route('exhibitions.application');
        }

        return view('exhibitions::applicant.register', [
            'countries' => CountryCatalog::all(),
            'detectedCountryCode' => CountryCatalog::detect($request),
            'agreementTemplate' => $this->verification->defaultAgreementTemplate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $applicationRules = ArtistApplicationRules::rules(true);
        unset(
            $applicationRules['legal_name'],
            $applicationRules['contact_email'],
            $applicationRules['artistic_name'],
            $applicationRules['contact_phone'],
            $applicationRules['country'],
            $applicationRules['social_accounts'],
            $applicationRules['social_accounts.*'],
        );
        $applicationRules['country_code'] = ['required', 'string', 'size:2', Rule::in(CountryCatalog::codes())];
        $applicationRules['contact_phone'] = ['required', 'string', 'regex:/^[0-9() .-]{5,30}$/'];
        $applicationRules['sample_artworks'] = [
            'required',
            'array',
            'min:1',
            'max:'.config('exhibitions.applications.sample_max_files', 8),
        ];
        $identityMax = config('exhibitions.verification.document_max_kilobytes', 10240);
        $applicationRules['identity_front'] = ['required_unless:identity_document_type,passport', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$identityMax];
        $applicationRules['identity_back'] = ['required_unless:identity_document_type,passport', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$identityMax];
        $applicationRules['identity_single'] = ['required_if:identity_document_type,passport', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$identityMax];

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! $this->users->emailIsAvailable((string) $value)) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
            'agreement_template' => ['required', 'uuid', $this->verification->activeAgreementTemplateRule()],
        ] + $applicationRules);

        $agreementTemplate = $this->verification->activeAgreementTemplate((string) $validated['agreement_template']);
        $name = trim($validated['full_name']);
        $applicationData = $validated;
        unset(
            $applicationData['full_name'],
            $applicationData['email'],
            $applicationData['password'],
            $applicationData['terms'],
            $applicationData['agreement_template'],
            $applicationData['country_code'],
        );
        $applicationData['legal_name'] = $name;
        $applicationData['contact_email'] = $validated['email'];
        $applicationData['artistic_name'] = $name;
        $applicationData['country'] = CountryCatalog::name($validated['country_code']);
        $applicationData['contact_phone'] = CountryCatalog::formatPhone($validated['country_code'], $validated['contact_phone']);

        $applicationPublicId = null;
        $verificationCasePublicId = null;
        $sampleDisk = (string) config('exhibitions.applications.sample_disk', 'local');
        $verificationDisk = (string) config('exhibitions.verification.document_disk', 'local');

        try {
            [$user] = DB::transaction(function () use ($request, $applicationData, $name, $validated, $agreementTemplate, &$applicationPublicId, &$verificationCasePublicId): array {
                $user = $this->users->create($name, (string) $validated['email'], (string) $validated['password']);
                $userId = (int) $user->getAuthIdentifier();
                $application = $this->applications->saveDraft($userId, $applicationData);
                $applicationPublicId = (string) $application->public_id;
                if ($this->applications->storeSamples($application, (array) $applicationData['sample_artworks']) < 1) {
                    throw new RuntimeException('At least one private sample artwork is required.');
                }
                $this->applications->storeSupportingDocuments($application, (array) ($applicationData['supporting_documents'] ?? []));

                $case = $this->verification->forApplication($application);
                $verificationCasePublicId = (string) $case->public_id;
                $this->verification->assignAgreement($case, $agreementTemplate, null);
                $this->storeIdentityDocuments($request, $case, $userId);
                $this->verification->acceptAgreement($case, $userId, [
                    'ip' => hash('sha256', (string) $request->ip()),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'application_public_id' => (string) $application->public_id,
                    'agreement_template_public_id' => (string) $agreementTemplate->public_id,
                    'accepted_during_registration' => true,
                ]);
                $this->applications->submit($userId, $applicationData);

                return [$user, $application];
            });
        } catch (Throwable $exception) {
            if ($applicationPublicId !== null) {
                Storage::disk($sampleDisk)->deleteDirectory('exhibitions/applications/'.$applicationPublicId);
            }
            if ($verificationCasePublicId !== null) {
                Storage::disk($verificationDisk)->deleteDirectory('exhibitions/verification/'.$verificationCasePublicId);
            }

            throw $exception;
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('status', 'artist-application-submitted');
    }

    private function storeIdentityDocuments(Request $request, object $case, int $userId): void
    {
        $slots = match ((string) $request->input('identity_document_type')) {
            'national_id' => ['identity_front' => 'identity_national_front', 'identity_back' => 'identity_national_back'],
            'driver_license' => ['identity_front' => 'identity_license_front', 'identity_back' => 'identity_license_back'],
            'passport' => ['identity_single' => 'identity_passport'],
            default => [],
        };

        foreach ($slots as $input => $documentType) {
            $this->verification->storeDocuments($case, $documentType, [$request->file($input)], $userId);
        }
    }
}
