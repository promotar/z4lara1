<?php

namespace Modules\Exhibition\Http\Applicant\Support;

use Illuminate\Validation\Rule;

final class ArtistApplicationRules
{
    /** @return array<string, mixed> */
    public static function rules(bool $submit): array
    {
        $required = $submit ? 'required' : 'nullable';

        return [
            'legal_name' => [$required, 'string', 'max:160'],
            'birth_date' => [$required, 'date', 'before:today'],
            'nationality' => [$required, 'string', 'max:100'],
            'artistic_name' => [$required, 'string', 'max:160'],
            'contact_email' => [$required, 'email:rfc', 'max:254'],
            'contact_phone' => [$required, 'string', 'regex:/^[+0-9() .-]{7,40}$/'],
            'city' => [$required, 'string', 'max:120'],
            'country' => [$required, 'string', 'max:120'],
            'biography' => [$required, 'string', $submit ? 'min:100' : 'min:0', 'max:5000'],
            'years_experience' => [$required, 'integer', 'between:0,100'],
            'qualifications' => ['nullable', 'string', 'max:4000'],
            'previous_exhibitions' => ['nullable', 'string', 'max:4000'],
            'awards_certificates' => ['nullable', 'string', 'max:4000'],
            'previous_work_links' => ['nullable', 'array', 'max:10'],
            'previous_work_links.*' => ['nullable', 'url:http,https', 'max:2048'],
            'social_accounts' => ['nullable', 'array', 'max:10'],
            'social_accounts.*' => ['nullable', 'url:http,https', 'max:2048'],
            'sample_artworks' => ['nullable', 'array', 'max:'.config('exhibitions.applications.sample_max_files', 8)],
            'sample_artworks.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('exhibitions.applications.sample_max_kilobytes', 8192)],
            'supporting_documents' => ['nullable', 'array', 'max:'.config('exhibitions.applications.supporting_document_max_files', 8)],
            'supporting_documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:'.config('exhibitions.applications.supporting_document_max_kilobytes', 10240)],
            'identity_document_type' => [$required, Rule::in(['national_id', 'passport', 'driver_license'])],
            'identity_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('exhibitions.verification.document_max_kilobytes', 10240)],
            'identity_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('exhibitions.verification.document_max_kilobytes', 10240)],
            'identity_single' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('exhibitions.verification.document_max_kilobytes', 10240)],
            'accept_artist_agreement' => [$submit ? 'accepted' : 'nullable'],
            'ownership_declaration' => [$submit ? 'accepted' : 'nullable'],
        ];
    }
}
