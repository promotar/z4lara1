<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentAssetBuildContractTest extends TestCase
{
    public function test_nixpacks_build_must_produce_a_vite_manifest(): void
    {
        $config = $this->projectFile('nixpacks.toml');
        $package = json_decode($this->projectFile('package.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('22.x', $package['engines']['node'] ?? null);
        self::assertStringContainsString('npm ci --include=dev', $config);
        self::assertStringContainsString('npm run build', $config);
        self::assertStringContainsString('test -s public/build/manifest.json', $config);
    }

    public function test_php_image_contains_a_verified_frontend_build(): void
    {
        $dockerfile = $this->projectFile('docker/php/Dockerfile');

        self::assertStringContainsString('FROM node:22-bookworm-slim AS vite-assets', $dockerfile);
        self::assertStringContainsString('FROM php:8.2-apache-bookworm', $dockerfile);
        self::assertStringContainsString('npm ci --include=dev', $dockerfile);
        self::assertStringContainsString('test -s public/build/manifest.json', $dockerfile);
        self::assertStringContainsString('a2enmod rewrite headers expires', $dockerfile);
        self::assertStringContainsString('a2enconf art-inpa-servername', $dockerfile);
        self::assertStringContainsString('COPY . /var/www/html', $dockerfile);
        self::assertStringContainsString('composer install', $dockerfile);
        self::assertStringContainsString('--no-dev', $dockerfile);
        self::assertStringContainsString('--no-scripts', $dockerfile);
        self::assertStringContainsString('rm -f bootstrap/cache/*.php', $dockerfile);
        self::assertStringContainsString('CMD ["apache2-foreground"]', $dockerfile);
        self::assertStringContainsString(
            'COPY --from=vite-assets /build/public/build /opt/art-inpa/public/build',
            $dockerfile,
        );
        self::assertStringContainsString('cp -a modules/. /opt/art-inpa/modules/', $dockerfile);
        self::assertStringContainsString('cp -a public/platform/. /opt/art-inpa/public/platform/', $dockerfile);
        self::assertStringNotContainsString('php-fpm', $dockerfile);
    }

    public function test_runtime_restores_only_the_prebuilt_artifact_and_fails_when_it_is_missing(): void
    {
        $entrypoint = $this->projectFile('docker/php/entrypoint.sh');

        self::assertStringContainsString('/opt/art-inpa/public/build/manifest.json', $entrypoint);
        self::assertStringContainsString('cp -R /opt/art-inpa/public/build/. public/build/', $entrypoint);
        self::assertStringContainsString('seed_missing_entries /opt/art-inpa/modules modules', $entrypoint);
        self::assertStringContainsString('seed_missing_entries /opt/art-inpa/public/platform public/platform', $entrypoint);
        self::assertStringContainsString('The deployment image is incomplete.', $entrypoint);
        self::assertStringContainsString('if [ -z "${APP_KEY:-}" ] && [ "$INSTALLATION_FLAG" != "1" ]; then', $entrypoint);
        self::assertStringContainsString('base64_encode(random_bytes(32))', $entrypoint);
        self::assertStringContainsString('export APP_KEY', $entrypoint);
        self::assertStringContainsString('file_put_contents($path, ltrim($content), LOCK_EX)', $entrypoint);
        self::assertStringContainsString('INSTAAL_IS_ACTIVE', $entrypoint);
        self::assertStringContainsString('INSTAAL_IS_ATIVE', $entrypoint);
        self::assertStringContainsString('INSTALLATION_COMPLETE', $entrypoint);
        self::assertStringContainsString('storage/app/platform/installation.complete', $entrypoint);
        self::assertStringContainsString('storage/app/platform/installation.env', $entrypoint);
        self::assertStringContainsString('@chmod($path, 0660)', $entrypoint);
        self::assertStringContainsString('The platform is marked as installed but APP_KEY is missing.', $entrypoint);
        self::assertStringContainsString('Existing installation detected; applying non-destructive database migrations', $entrypoint);
        self::assertStringContainsString('php artisan migrate --force --no-interaction', $entrypoint);
        self::assertStringNotContainsString('npm install', $entrypoint);
        self::assertStringNotContainsString('npm run build', $entrypoint);
        self::assertStringNotContainsString('apt-get install', $entrypoint);
    }

    public function test_first_run_environment_does_not_hard_code_an_http_origin_or_proxy(): void
    {
        $environment = $this->projectFile('.env.example');
        $bootstrap = $this->projectFile('bootstrap/app.php');

        self::assertMatchesRegularExpression('/^APP_URL=$/m', $environment);
        self::assertMatchesRegularExpression('/^TRUSTED_PROXIES=$/m', $environment);
        self::assertStringContainsString("\$trustedProxies = '*'", $bootstrap);
        self::assertStringContainsString("\$trustedProxies = '127.0.0.1,::1'", $bootstrap);
        self::assertStringNotContainsString("URL::forceScheme('https')", $bootstrap);
    }

    public function test_local_generated_assets_cannot_leak_into_the_image_build_context(): void
    {
        $dockerignore = $this->projectFile('.dockerignore');

        self::assertMatchesRegularExpression('/^public\/build$/m', $dockerignore);
        self::assertMatchesRegularExpression('/^public\/hot$/m', $dockerignore);
        self::assertMatchesRegularExpression('/^storage\/app$/m', $dockerignore);
    }

    public function test_runtime_never_redirects_the_public_root_to_public_html(): void
    {
        foreach ([
            'app/Providers/AppServiceProvider.php',
            'docker-compose.yml',
            'docker-compose.prod.yml',
            'docker/apache-vhost.conf',
            'nginx.template.conf',
        ] as $path) {
            self::assertStringNotContainsString('public_html', $this->projectFile($path), $path);
        }

        self::assertStringNotContainsString(
            'usePublicPath(',
            $this->projectFile('app/Providers/AppServiceProvider.php'),
        );
        self::assertStringContainsString(
            'root /app/public;',
            $this->projectFile('nginx.template.conf'),
        );
        self::assertStringContainsString(
            'DocumentRoot /var/www/html/public',
            $this->projectFile('docker/apache-vhost.conf'),
        );
    }

    public function test_compose_runtime_contains_only_the_laravel_app_service(): void
    {
        foreach (['docker-compose.yml', 'docker-compose.prod.yml'] as $path) {
            $compose = $this->projectFile($path);

            self::assertMatchesRegularExpression('/^services:\R  app:\R/m', $compose, $path);
            self::assertStringContainsString('docker/php/Dockerfile', $compose, $path);
            self::assertStringContainsString("expose:\n      - \"80\"", $compose, $path);
            self::assertStringNotContainsString("\n    ports:", $compose, $path);
            self::assertStringNotContainsString("\n  web:", $compose, $path);
            self::assertStringNotContainsString("\n  queue:", $compose, $path);
            self::assertStringNotContainsString("\n  scheduler:", $compose, $path);
            self::assertStringNotContainsString("\n  vite:", $compose, $path);
            self::assertStringNotContainsString('nginx:', $compose, $path);
            self::assertStringNotContainsString('php-fpm', $compose, $path);
            self::assertStringNotContainsString('env_file:', $compose, $path);
            self::assertStringNotContainsString('./:/var/www/html', $compose, $path);
            self::assertStringContainsString('art-inpa-storage:/var/www/html/storage', $compose, $path);
            self::assertStringContainsString('art-inpa-modules:/var/www/html/modules', $compose, $path);
            self::assertStringContainsString('art-inpa-platform-assets:/var/www/html/public/platform', $compose, $path);
        }

        $developmentCompose = $this->projectFile('docker-compose.dev.yml');
        self::assertStringContainsString('127.0.0.1:${ART_INPA_HTTP_PORT:-8088}:80', $developmentCompose);
        self::assertStringContainsString('./modules:/var/www/html/modules', $developmentCompose);
        self::assertStringContainsString('./public/platform:/var/www/html/public/platform', $developmentCompose);

        self::assertSame('sync', $this->environmentValue('QUEUE_CONNECTION'));
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertIsString($contents, $path.' must exist and be readable.');

        return $contents;
    }

    private function environmentValue(string $key): string
    {
        $environment = $this->projectFile('.env.example');
        preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $environment, $matches);

        self::assertArrayHasKey(1, $matches, "{$key} must be declared in .env.example.");

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }
}
