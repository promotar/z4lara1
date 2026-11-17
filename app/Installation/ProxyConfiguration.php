<?php

namespace App\Installation;

use Illuminate\Http\Request;

final class ProxyConfiguration
{
    private const LOCAL_PROXIES = '127.0.0.1,::1';

    public function __construct(private readonly ?string $configuredProxies = null) {}

    public function publicUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    public function trustedProxies(Request $request): string
    {
        $configured = trim($this->configuredProxies ?? (string) env('TRUSTED_PROXIES', ''));
        if ($configured !== '' && ! ($configured === self::LOCAL_PROXIES && $this->hasForwardedHeaders($request))) {
            return $configured;
        }

        if (! $this->hasForwardedHeaders($request)) {
            return self::LOCAL_PROXIES;
        }

        $remoteAddress = (string) $request->server('REMOTE_ADDR', '');
        if (filter_var($remoteAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $remoteAddress);

            return sprintf('%s.%s.%s.0/24', $octets[0], $octets[1], $octets[2]);
        }

        if (filter_var($remoteAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $remoteAddress.'/128';
        }

        return 'REMOTE_ADDR';
    }

    private function hasForwardedHeaders(Request $request): bool
    {
        return $request->headers->has('X-Forwarded-Proto')
            || $request->headers->has('X-Forwarded-Host')
            || $request->headers->has('X-Forwarded-For');
    }
}
