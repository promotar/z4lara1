<?php

namespace Modules\Exhibition\Domain\Verification;

enum VerificationState: string
{
    case Pending = 'pending';
    case DocumentsRequired = 'documents_required';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('exhibitions::ui.verification_state_'.$this->value);
    }
}
