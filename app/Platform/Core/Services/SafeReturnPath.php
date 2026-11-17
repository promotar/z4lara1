<?php

namespace App\Platform\Core\Services;

final class SafeReturnPath
{
    /** @var list<string> */
    private const BLOCKED_PREFIXES = [
        '/login',
        '/logout',
        '/register',
        '/verify-email',
        '/email/verification-notification',
        '/forgot-password',
        '/reset-password',
        '/confirm-password',
        '/install',
    ];

    public function sanitize(mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === ''
            || ! str_starts_with($candidate, '/')
            || str_starts_with($candidate, '//')
            || str_contains($candidate, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return null;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        foreach (['scheme', 'host', 'port', 'user', 'pass', 'fragment'] as $externalPart) {
            if (array_key_exists($externalPart, $parts)) {
                return null;
            }
        }

        $path = (string) ($parts['path'] ?? '');
        $decodedPath = rawurldecode($path);

        if ($path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($decodedPath, '//')
            || str_contains($decodedPath, '\\')
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $decodedPath) === 1) {
            return null;
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return null;
            }
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}
