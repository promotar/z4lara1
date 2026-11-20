<?php

namespace Modules\Exhibition\Application\Security;

final class PrivateResponseHeaders
{
    /** @return array<string, string> */
    public static function for(?string $contentType = null): array
    {
        return [
            'Content-Type' => $contentType ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
