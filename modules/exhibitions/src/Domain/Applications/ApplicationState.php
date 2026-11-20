<?php

namespace Modules\Exhibition\Domain\Applications;

enum ApplicationState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocumentsRequired = 'documents_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ContractPending = 'contract_pending';
    case Active = 'active';

    public function label(): string
    {
        return __('exhibitions::ui.application_state_'.$this->value);
    }
}
