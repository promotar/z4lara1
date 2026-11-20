<?php

namespace Modules\Exhibition\Application\Verification;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Domain\Verification\VerificationState;

final class ActivationEligibility
{
    public function assertEligible(int $applicationId): void
    {
        $applicationState = DB::table('exhibition_artist_applications')->where('id', $applicationId)->value('state');
        if ($applicationState === 'active') {
            return;
        }

        $case = DB::table('exhibition_verification_cases')->where('application_id', $applicationId)->first();
        if ($case === null || $case->state !== VerificationState::Verified->value) {
            throw new DomainException('Identity review must be completed before artwork can be submitted for sale.');
        }

        $agreement = DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->first();
        if ($agreement === null || $agreement->accepted_at === null || $agreement->accepted_by === null) {
            throw new DomainException('The assigned artist agreement must be accepted before artwork can be submitted for sale.');
        }

        $payout = DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->first();
        if ($payout === null || $payout->verified_at === null || $payout->verified_by === null) {
            throw new DomainException('Verified payout information is required before artwork can be submitted for sale.');
        }
    }

    /** @return array<string, bool> */
    public function checklist(int $applicationId): array
    {
        if (DB::table('exhibition_artist_applications')->where('id', $applicationId)->value('state') === 'active') {
            return ['verification' => true, 'agreement' => true, 'payout' => true];
        }

        $case = DB::table('exhibition_verification_cases')->where('application_id', $applicationId)->first();
        if ($case === null) {
            return ['verification' => false, 'agreement' => false, 'payout' => false];
        }

        return [
            'verification' => $case->state === VerificationState::Verified->value,
            'agreement' => DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->whereNotNull('accepted_at')->exists(),
            'payout' => DB::table('exhibition_payout_profiles')->where('verification_case_id', $case->id)->exists(),
        ];
    }

    /** @return array{completed:int,total:int,percentage:int,ready:bool,items:array<string,bool>} */
    public function progress(int $applicationId): array
    {
        $items = $this->checklist($applicationId);
        $completed = count(array_filter($items));
        $total = count($items);

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'ready' => $total > 0 && $completed === $total,
            'items' => $items,
        ];
    }

    /** @return array{identity:bool,agreement:bool} */
    public function sellingChecklist(int $applicationId): array
    {
        if (DB::table('exhibition_artist_applications')->where('id', $applicationId)->value('state') === 'active') {
            return ['identity' => true, 'agreement' => true];
        }
        $case = DB::table('exhibition_verification_cases')->where('application_id', $applicationId)->first();
        if ($case === null) {
            return ['identity' => false, 'agreement' => false];
        }

        return [
            'identity' => $case->state === VerificationState::Verified->value,
            'agreement' => DB::table('exhibition_artist_agreements')->where('verification_case_id', $case->id)->whereNotNull('accepted_at')->exists(),
        ];
    }

    public function readyToSell(int $applicationId): bool
    {
        $items = $this->sellingChecklist($applicationId);

        return ! in_array(false, $items, true);
    }
}
