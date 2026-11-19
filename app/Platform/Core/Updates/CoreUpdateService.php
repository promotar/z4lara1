<?php

namespace App\Platform\Core\Updates;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Applies a packed platform (core) update from an uploaded zip and restores the
 * previous files automatically when anything fails.
 *
 * Safety rules:
 *  - .env, storage, vendor, modules, node_modules, .git and public/storage are never replaced.
 *  - Every overwritten file is copied to a snapshot before the swap.
 *  - New files are tracked so a rollback can remove them.
 *  - Maintenance mode is enabled while files are swapped and always disabled again.
 */
final class CoreUpdateService
{
    private string $root;

    private string $workDir;

    public function __construct()
    {
        $this->root = base_path();
        $this->workDir = storage_path('app/private/core-updates');
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        $history = $this->history();
        $last = $history[0] ?? null;

        $rollbackTarget = null;

        foreach ($history as $entry) {
            if (($entry['status'] ?? '') === 'success' && is_dir($this->workDir.'/snapshots/'.($entry['id'] ?? ''))) {
                $rollbackTarget = $entry;
                break;
            }
        }

        return [
            'version' => (string) config('platform.release', config('platform.version')),
            'last' => $last,
            'history' => array_slice($history, 0, 5),
            'can_rollback' => $rollbackTarget !== null,
            'rollback_id' => $rollbackTarget['id'] ?? null,
        ];
    }

    /**
     * @return array{ok:bool,message:string,id:?string}
     */
    public function apply(UploadedFile $file): array
    {
        $id = date('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6);
        $tmp = $this->workDir.'/tmp/'.$id;
        $snapshot = $this->workDir.'/snapshots/'.$id;

        File::ensureDirectoryExists($tmp);
        File::ensureDirectoryExists($snapshot);

        $written = false;

        try {
            $zipPath = $tmp.'/package.zip';
            $file->move($tmp, 'package.zip');

            $source = $this->extract($zipPath, $tmp);
            $files = $this->packageFiles($source);

            if ($files === []) {
                throw new \RuntimeException('الحزمة لا تحتوي ملفات صالحة للتحديث.');
            }

            $this->assertLooksLikePlatform($files);

            $state = $this->snapshot($source, $files, $snapshot, $id, $file->getClientOriginalName());

            Artisan::call('down');

            try {
                $this->copyFiles($source, $files);
                $written = true;

                $this->dumpAutoload();
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('optimize:clear');
            } catch (Throwable $exception) {
                $this->restore($state, $snapshot);
                throw $exception;
            } finally {
                Artisan::call('up');
            }

            $this->appendHistory([
                'id' => $id,
                'file' => $file->getClientOriginalName(),
                'sha256' => hash_file('sha256', $zipPath) ?: '',
                'files' => count($files),
                'status' => 'success',
                'message' => __('core.updates.flash.applied'),
                'at' => now()->toIso8601String(),
            ]);

            File::deleteDirectory($tmp);

            return ['ok' => true, 'message' => __('core.updates.flash.applied_with_backup').count($files).' ملفاً).', 'id' => $id];
        } catch (Throwable $exception) {
            if ($written) {
                // restore() already ran inside the inner catch; keep the guard for early failures.
            }

            Log::error('core update failed', ['id' => $id, 'error' => $exception->getMessage()]);

            $this->appendHistory([
                'id' => $id,
                'file' => $file->getClientOriginalName(),
                'sha256' => '',
                'files' => 0,
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'at' => now()->toIso8601String(),
            ]);

            File::deleteDirectory($tmp);

            $reason = trim(strtok($exception->getMessage(), "\n") ?: $exception->getMessage());
            $reason = \Illuminate\Support\Str::limit($reason, 180);

            return ['ok' => false, 'message' => __('core.updates.flash.apply_failed').$reason, 'id' => $id];
        }
    }

    /** @return array{ok:bool,message:string} */
    public function rollback(): array
    {
        $last = null;

        foreach ($this->history() as $entry) {
            if (($entry['status'] ?? '') === 'success') {
                $last = $entry;
                break;
            }
        }

        if ($last === null) {
            return ['ok' => false, 'message' => __('core.updates.rollback.none')];
        }

        $snapshot = $this->workDir.'/snapshots/'.$last['id'];
        $stateFile = $snapshot.'/state.json';

        if (! is_file($stateFile)) {
            return ['ok' => false, 'message' => __('core.updates.rollback.state_missing')];
        }

        try {
            $state = json_decode((string) file_get_contents($stateFile), true) ?: [];
            $this->restore($state, $snapshot);
            Artisan::call('optimize:clear');

            $this->appendHistory([
                'id' => $last['id'].'-rollback',
                'file' => $last['file'] ?? '',
                'sha256' => '',
                'files' => count($state['existing'] ?? []),
                'status' => 'rolled-back',
                'message' => __('core.updates.rollback.manual'),
                'at' => now()->toIso8601String(),
            ]);

            return ['ok' => true, 'message' => __('core.updates.rollback.done')];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => __('core.updates.rollback.failed').$exception->getMessage()];
        }
    }

    private function extract(string $zipPath, string $tmp): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('تعذّر فتح ملف ZIP.');
        }

        $target = $tmp.'/extracted';
        File::ensureDirectoryExists($target);
        $zip->extractTo($target);
        $zip->close();

        // Package root: the folder that contains artisan (z4lara1-main, or the archive root).
        foreach (File::directories($target) as $dir) {
            if (is_file($dir.'/artisan')) {
                return $dir;
            }
        }

        return $target;
    }

    /** @return list<string> */
    private function packageFiles(string $source): array
    {
        $skipTop = ['.env', '.env.example', 'storage', 'vendor', 'modules', 'node_modules', '.git', 'public/storage'];
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (! $item->isFile()) {
                continue;
            }

            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));

            foreach ($skipTop as $skip) {
                if ($rel === $skip || str_starts_with($rel, $skip.'/')) {
                    continue 2;
                }
            }

            $files[] = $rel;
        }

        sort($files);

        return $files;
    }

    /** @param list<string> $files */
    private function assertLooksLikePlatform(array $files): void
    {
        $required = ['artisan', 'composer.json'];

        foreach ($required as $needle) {
            if (! in_array($needle, $files, true)) {
                throw new \RuntimeException('الحزمة ليست تحديث كور صالح (المفتاح '.$needle.' مفقود).');
            }
        }
    }

    /**
     * @param list<string> $files
     * @return array{existing:list<string>,added:list<string>,file:string}
     */
    private function snapshot(string $source, array $files, string $snapshot, string $id, string $original): array
    {
        $existing = [];
        $added = [];

        foreach ($files as $rel) {
            $target = $this->root.'/'.$rel;

            if (is_file($target)) {
                $dest = $snapshot.'/files/'.$rel;
                File::ensureDirectoryExists(dirname($dest));
                File::copy($target, $dest);
                $existing[] = $rel;
            } else {
                $added[] = $rel;
            }
        }

        $state = [
            'existing' => $existing,
            'added' => $added,
            'file' => $original,
            'id' => $id,
            'at' => now()->toIso8601String(),
        ];

        File::put($snapshot.'/state.json', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $state;
    }

    /** @param list<string> $files */
    private function copyFiles(string $source, array $files): void
    {
        // ملفات البناء/البنية التحتية لا تُستبدل بتحديث الكور.
        $infra = [
            '.dockerignore', '.gitignore', '.gitattributes', '.editorconfig',
            'Dockerfile', 'nixpacks.toml', 'nginx.template.conf', 'phpunit.xml',
            'package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
            'README.md', 'DEPLOYMENT.md', 'LOCAL_DOCKER.md', 'SETUP_GUIDE.md', 'TROUBLESHOOTING.md',
            'project.txt', 'design-qa.md',
        ];

        foreach ($files as $rel) {
            if (in_array($rel, $infra, true)) {
                continue;
            }

            if (preg_match('/^docker-compose.*\.ya?ml$/', $rel) === 1) {
                continue;
            }

            if (preg_match('#^(docker|docs|ops|reports|scripts|tests|verification)/#', $rel) === 1) {
                continue;
            }

            $from = $source.'/'.$rel;
            $to = $this->root.'/'.$rel;

            File::ensureDirectoryExists(dirname($to));

            if (is_file($to) && ! is_writable($to)) {
                throw new \RuntimeException(
                    'تعذّر الكتابة على الملف: '.$rel
                    .' — ملفات التطبيق يجب أن تكون قابلة للكتابة لمستخدم الويب (www-data).'
                    .' نفّذ chown -R www-data:www-data على مجلدات التطبيق ثم أعد المحاولة.'
                );
            }

            if (! File::copy($from, $to)) {
                throw new \RuntimeException('تعذّر نسخ الملف: '.$rel.' — تحقّق من صلاحيات الكتابة لمستخدم الويب (www-data).');
            }
        }
    }

    /** @param array<string,mixed> $state */
    private function restore(array $state, string $snapshot): void
    {
        foreach (($state['added'] ?? []) as $rel) {
            $target = $this->root.'/'.$rel;

            if (is_file($target)) {
                @unlink($target);
            }
        }

        foreach (($state['existing'] ?? []) as $rel) {
            $backup = $snapshot.'/files/'.$rel;

            if (is_file($backup)) {
                File::ensureDirectoryExists(dirname($this->root.'/'.$rel));
                @copy($backup, $this->root.'/'.$rel);
            }
        }
    }

    private function dumpAutoload(): void
    {
        $composer = trim((string) shell_exec('command -v composer 2>/dev/null'));

        if ($composer === '') {
            return;
        }

        // Apache/PHP-FPM لا يوفّر HOME؛ وComposer يحتاجه ليعمل.
        $composerHome = storage_path('app/private/composer');
        File::ensureDirectoryExists($composerHome);

        $env = [
            'HOME' => $composerHome,
            'COMPOSER_HOME' => $composerHome,
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'PATH' => (string) (getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'),
        ];

        $process = new Process([$composer, 'dump-autoload', '--optimize', '--no-interaction'], $this->root, $env);
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            $reason = trim($process->getErrorOutput() ?: $process->getOutput());
            $reason = preg_replace('/\s+/', ' ', $reason) ?? $reason;

            throw new \RuntimeException(
                'composer dump-autoload فشل: '.\Illuminate\Support\Str::limit($reason, 220)
                .' — تأكد أن مجلد vendor قابل للكتابة لمستخدم الويب (www-data).'
            );
        }
    }

    /** @return list<array<string,mixed>> */
    private function history(): array
    {
        $file = $this->workDir.'/history.json';

        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed> $entry */
    private function appendHistory(array $entry): void
    {
        File::ensureDirectoryExists($this->workDir);

        $history = $this->history();
        array_unshift($history, $entry);
        $history = array_slice($history, 0, 20);

        File::put($this->workDir.'/history.json', json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
