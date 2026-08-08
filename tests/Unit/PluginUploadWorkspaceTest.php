<?php

namespace Tests\Unit;

use App\Platform\Core\Services\PluginUploadWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginUploadWorkspaceTest extends TestCase
{
    public function test_archives_and_extraction_directories_use_the_private_plugin_workspace(): void
    {
        $root = storage_path('framework/testing/plugin-upload-workspace');
        File::deleteDirectory($root);

        config()->set('filesystems.disks.plugin_uploads.root', $root);

        $workspace = app(PluginUploadWorkspace::class);
        $temporary = $workspace->store(
            UploadedFile::fake()->createWithContent('plugin.zip', 'plugin archive'),
        );
        $extraction = $workspace->createExtractionDirectory('test-plugin');
        $pending = $workspace->preserveForUpdate($temporary, str_repeat('a', 40));

        $this->assertStringStartsWith('tmp/', $temporary);
        $this->assertStringStartsWith('extracted/test-plugin-', $extraction);
        $this->assertSame('pending_updates/'.str_repeat('a', 40).'.zip', $pending);
        $this->assertFileExists($workspace->absolutePath($temporary));
        $this->assertDirectoryExists($workspace->absolutePath($extraction));
        $this->assertFileExists($workspace->absolutePath($pending));

        $workspace->discardFile($temporary);
        $workspace->discardFile($pending);
        $workspace->discardDirectory($extraction);

        $this->assertFileDoesNotExist($root.'/'.$temporary);
        $this->assertFileDoesNotExist($root.'/'.$pending);
        $this->assertDirectoryDoesNotExist($root.'/'.$extraction);

        File::deleteDirectory($root);
    }

    public function test_workspace_rejects_paths_outside_its_private_root(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid internal path');

        app(PluginUploadWorkspace::class)->absolutePath('../outside.zip');
    }
}
