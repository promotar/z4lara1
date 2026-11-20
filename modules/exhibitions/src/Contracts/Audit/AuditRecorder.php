<?php

namespace Modules\Exhibition\Contracts\Audit;

interface AuditRecorder
{
    public function record(AuditEntryData $entry): string;
}
