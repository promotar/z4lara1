<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The modules volume is the source of truth for installed plugins. The image
 * may seed a missing module or upgrade it when the release ships a newer
 * version, but a restart must never roll back deployed changes.
 */
class EntrypointModuleSyncContractTest extends TestCase
{
    public function test_required_modules_are_never_overwritten_without_a_version_bump(): void
    {
        $script = $this->projectFile('docker/php/entrypoint.sh');

        self::assertStringContainsString('module_version()', $script);
        self::assertStringContainsString("sort -V", $script);
        self::assertStringContainsString('Seeding required module', $script);
        self::assertStringContainsString('Upgrading required module', $script);
        self::assertStringContainsString('Keeping installed module', $script);

        $seedPosition = strpos($script, 'Seeding required module');
        $upgradePosition = strpos($script, 'Upgrading required module');
        $copyPosition = strpos($script, 'cp -a "$source_directory/."');

        self::assertNotFalse($seedPosition);
        self::assertNotFalse($upgradePosition);
        self::assertNotFalse($copyPosition);

        self::assertGreaterThan($seedPosition, $copyPosition, 'The seed copy must run after the missing-module branch.');
        self::assertGreaterThan($upgradePosition, $copyPosition, 'The overwrite copy must run only after a version upgrade is decided.');
    }

    public function test_required_modules_keep_the_installed_copy_when_versions_are_equal_or_unknown(): void
    {
        $script = $this->projectFile('docker/php/entrypoint.sh');

        self::assertStringContainsString(
            'if [ "$newest_version" = "$target_version" ]; then',
            $script,
        );
        self::assertStringContainsString(
            'if [ -z "$source_version" ] || [ -z "$target_version" ]; then',
            $script,
        );
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertIsString($contents, $path.' must exist and be readable.');

        return $contents;
    }
}
