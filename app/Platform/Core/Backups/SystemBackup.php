<?php

namespace App\Platform\Core\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Real system backup: a full database dump plus a code-only archive.
 *
 * The code archive intentionally excludes every kind of media and uploaded
 * document. Only source code, styles and the icons/images shipped with the
 * code are kept.
 */
final class SystemBackup
{
    public const RETENTION = 5;

    /** Directories (relative to the app root) that belong to the code. */
    private const CODE_PATHS = [
        'app',
        'bootstrap',
        'config',
        'database',
        'lang',
        'modules',
        'resources',
        'routes',
        'public/build',
        'public/admin-assets',
        'public/platform',
    ];

    /** Individual files that belong to the code. */
    private const CODE_FILES = [
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'tailwind.config.js',
        'vite.config.js',
        'postcss.config.js',
        'phpunit.xml',
        'public/index.php',
        'public/.htaccess',
        'public/favicon.ico',
        'public/robots.txt',
    ];

    /** Folders that can never be part of a code backup. */
    private const EXCLUDED_SEGMENTS = [
        '/storage/',
        '/node_modules/',
        '/.git/',
        '/vendor/',
        '/public/storage/',
        '/uploads/',
        '/uploaded/',
        '/media/',
        '/backups/',
        '/cache/',
        '/logs/',
        '/sessions/',
    ];

    /** Media and document extensions — never copied. */
    private const EXCLUDED_EXTENSIONS = [
        'mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'mpg', 'mpeg', '3gp',
        'mp3', 'wav', 'm4a', 'ogg', 'flac', 'aac', 'wma', 'opus',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'rtf',
        'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2',
        'sql', 'sqlite', 'db', 'bak', 'log',
    ];

    /**
     * @return array{db: string, code: string, db_size: int, code_size: int, files: int}
     */
    public function create(string $label = 'manual'): array
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $stamp = now()->format('Ymd-His');
        $dbPath = $directory.DIRECTORY_SEPARATOR.'db-'.$stamp.'.sql.gz';
        $codePath = $directory.DIRECTORY_SEPARATOR.'code-'.$stamp.'-'.$this->safe($label).'.zip';

        $tables = $this->dumpDatabase($dbPath);
        $files = $this->archiveCode($codePath, $label);

        $this->prune('db-', self::RETENTION);
        $this->prune('code-', self::RETENTION);

        return [
            'db' => $dbPath,
            'code' => $codePath,
            'db_size' => (int) @filesize($dbPath),
            'code_size' => (int) @filesize($codePath),
            'files' => $files,
            'tables' => $tables,
        ];
    }

    // ---------------------------------------------------------------- database

    private function dumpDatabase(string $path): int
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $database = $connection->getDatabaseName();

        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new RuntimeException('Cannot open the database dump file for writing.');
        }

        $write = static function (string $chunk) use ($handle): void {
            gzwrite($handle, $chunk);
        };

        $write("-- Art INPA database backup\n");
        $write('-- Database: '.$database.' — '.now()->toDateTimeString()."\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM);
        $tableCount = 0;

        foreach ($tables as $table) {
            $name = (string) $table[0];
            $type = strtoupper((string) ($table[1] ?? 'BASE TABLE'));

            if ($type !== 'BASE TABLE') {
                $write("-- Skipped non-table object: {$name}\n\n");

                continue;
            }

            $tableCount++;

            $create = $pdo->query('SHOW CREATE TABLE `'.str_replace('`', '``', $name).'`')->fetch(\PDO::FETCH_NUM);
            $write("DROP TABLE IF EXISTS `{$name}`;\n".($create[1] ?? '').";\n\n");

            try {
                $rows = $pdo->query('SELECT * FROM `'.str_replace('`', '``', $name).'`');
            } catch (\Throwable) {
                continue;
            }

            $batch = [];
            $count = 0;

            while (($row = $rows->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $values = array_map(
                    static fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row),
                );

                $batch[] = '('.implode(',', $values).')';
                $count++;

                if ($count % 200 === 0) {
                    $write('INSERT INTO `'.$name.'` VALUES '.implode(',', $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $write('INSERT INTO `'.$name.'` VALUES '.implode(',', $batch).";\n");
            }

            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($handle);

        return $tableCount;
    }

    // -------------------------------------------------------------------- code

    private function archiveCode(string $path, string $label): int
    {
        $root = base_path();
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the code archive.');
        }

        $added = 0;

        foreach (self::CODE_PATHS as $relative) {
            $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $full = $file->getPathname();
                $inside = str_replace('\\', '/', substr($full, strlen($root) + 1));

                if ($this->isExcluded($inside)) {
                    continue;
                }

                $zip->addFile($full, $inside);
                $added++;
            }
        }

        foreach (self::CODE_FILES as $relative) {
            $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_file($absolute) || $this->isExcluded($relative)) {
                continue;
            }

            $zip->addFile($absolute, $relative);
            $added++;
        }

        $zip->addFromString(
            'BACKUP-INFO.txt',
            "Art INPA code backup\n"
            .'Created: '.now()->toDateTimeString()."\n"
            .'Label: '.$label."\n"
            .'Files: '.$added."\n\n"
            ."Excluded on purpose: storage (media, uploads, private documents), vendor, node_modules,\n"
            ."database dumps, logs, archives, and every video/audio/document file type.\n",
        );

        $zip->close();

        return $added;
    }

    private function isExcluded(string $inside): bool
    {
        $path = '/'.strtolower(str_replace('\\', '/', $inside)).'/';

        foreach (self::EXCLUDED_SEGMENTS as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        $extension = strtolower((string) pathinfo($inside, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::EXCLUDED_EXTENSIONS, true);
    }

    // ----------------------------------------------------------------- pruning

    private function prune(string $prefix, int $keep): void
    {
        $files = glob(storage_path('app/backups').DIRECTORY_SEPARATOR.$prefix.'*') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    private function safe(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?: 'backup';

        return trim($clean, '-');
    }
}
