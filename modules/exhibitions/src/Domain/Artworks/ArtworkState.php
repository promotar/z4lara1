<?php

namespace Modules\Exhibition\Domain\Artworks;

enum ArtworkState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequired = 'changes_required';
    case Approved = 'approved';
    case Published = 'published';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function artistEditable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::ChangesRequired, self::Rejected], true);
    }
}
