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
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'art.example.com',
        ]);

        $configuration = new ProxyConfiguration('');

        self::assertSame('172.18.0.0/24', $configuration->trustedProxies($request));
    }

    public function test_direct_installation_keeps_only_local_proxies_trusted(): void
    {
        $request = Request::create('http://127.0.0.1/install', 'GET', server: [
            'REMOTE_ADDR' => '192.168.1.25',
        ]);

        self::assertSame('127.0.0.1,::1', (new ProxyConfiguration(''))->trustedProxies($request));
    }

    public function test_explicit_proxy_configuration_is_never_replaced_by_detection(): void
    {
        $request = Request::create('http://internal/install', 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(
            '10.20.0.0/16',
            (new ProxyConfiguration('10.20.0.0/16'))->trustedProxies($request),
        );
    }

    public function test_legacy_local_default_is_upgraded_when_forwarded_headers_are_present(): void
    {
        $request = Request::create('http://internal/install', 'GET', server: [
            'REMOTE_ADDR' => '172.19.0.8',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame(
            '172.19.0.0/24',
            (new ProxyConfiguration('127.0.0.1,::1'))->trustedProxies($request),
        );
    }
}
