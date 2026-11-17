<?php

namespace Tests\Unit;

use App\Installation\ProxyConfiguration;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class InstallationProxyConfigurationTest extends TestCase
{
    public function test_forwarded_installation_request_persists_the_calling_proxy_network(): void
    {
        $request = Request::create('http://internal/install', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'art.example.com',
        ]);

        $configuration = new ProxyConfiguration('');

        self::assertSame('198.51.100.0/24', $configuration->trustedProxies($request));
    }

    public function test_direct_installation_keeps_only_local_proxies_trusted(): void
    {
        $request = Request::create('http://127.0.0.1/install', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.25',
        ]);

        self::assertSame('127.0.0.1,::1', (new ProxyConfiguration(''))->trustedProxies($request));
    }

    public function test_explicit_proxy_configuration_is_never_replaced_by_detection(): void
    {
        $request = Request::create('http://internal/install', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(
            '198.51.100.0/24',
            (new ProxyConfiguration('198.51.100.0/24'))->trustedProxies($request),
        );
    }

    public function test_legacy_local_default_is_upgraded_when_forwarded_headers_are_present(): void
    {
        $request = Request::create('http://internal/install', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.8',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(
            '203.0.113.0/24',
            (new ProxyConfiguration('127.0.0.1,::1'))->trustedProxies($request),
        );
    }
}
