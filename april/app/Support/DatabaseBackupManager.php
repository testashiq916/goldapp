<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use ZipArchive;

class DatabaseBackupManager
{
    private const GENERAL_AUTO_ENABLED = 'AUTOBACKUP';
    private const GENERAL_AUTO_TIME = 'BACKUPTIME';
    private const GENERAL_AUTO_LAST_RUN = 'ABKDATE';
    private const GENERAL_AUTO_TIMES = 'BKTIMES';
    private const GENERAL_AUTO_RUN_SLOTS = 'ABKRUNS';
    private const IMPORTANT_SAVE_LOCK = 'important-save.lock';

    public function runManualBackup(string $reason = 'manual'): array
    {
        return $this->createBackupSet($reason, true);
    }

    public function runImportantSaveBackup(string $reason = 'important-save'): array
    {
        if (!$this->acquireLock(self::IMPORTANT_SAVE_LOCK, 900)) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Important-save backup already running',
            ];
        }

        try {
            return $this->createBackupSet($reason, false);
        } finally {
            $this->releaseLock(self::IMPORTANT_SAVE_LOCK);
        }
    }

    public function runScheduledBackupsIfDue(bool $force = false): array
    {
        if (!$force && !$this->isAutoBackupEnabled()) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Auto backup disabled'];
        }

        $dueTime = $force ? 'forced' : $this->dueBackupTime();
        if (!$force && $dueTime === null) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Backup is not due yet'];
        }

        $result = $this->createBackupSet('scheduled', true);

        if (($result['ok'] ?? false) && Schema::hasTable('generals')) {
            DB::table('generals')->updateOrInsert(
                ['code' => self::GENERAL_AUTO_LAST_RUN],
                ['cvalue' => date('Y-m-d')]
            );
            DB::table('generals')->updateOrInsert(
                ['code' => self::GENERAL_AUTO_RUN_SLOTS],
                ['cvalue' => $this->markRunSlot($force ? date('H:i') : (string) $dueTime)]
            );
        }

        return $result;
    }

    public function listBackups(): array
    {
        $root = $this->backupRoot();
        $files = File::exists($root) ? File::allFiles($root) : [];
        $backups = [];

        foreach ($files as $file) {
            $name = $file->getFilename();
            if (!str_ends_with(strtolower($name), '.zip') && !str_ends_with(strtolower($name), '.sql.gz')) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), '\\/'));

            $backups[] = [
                'name' => $relative,
                'size' => round($file->getSize() / 1024 / 1024, 2),
                'date' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        usort($backups, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $backups;
    }

    public function backupRoot(): string
    {
        $dir = storage_path('app/backups');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    public function backupBucketDir(string $bucket): string
    {
        $bucket = trim($bucket) !== '' ? trim($bucket) : 'misc';
        $dir = $this->backupRoot() . DIRECTORY_SEPARATOR . $bucket;
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    public function deleteBackup(string $relativeFile): bool
    {
        $relativeFile = str_replace(['..\\', '../'], '', str_replace('\\', '/', trim($relativeFile)));
        $path = $this->backupRoot() . DIRECTORY_SEPARATOR . $relativeFile;

        if (!File::exists($path)) {
            return false;
        }

        File::delete($path);
        return true;
    }

    public function primaryDatabaseName(): string
    {
        return trim((string) Config::get('database.connections.mysql.database', ''));
    }

    public function secondaryDatabaseName(): string
    {
        $path = storage_path('app/company-dbstrf.json');
        if (!File::exists($path)) {
            return '';
        }

        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? trim((string) ($decoded['dbstrf'] ?? '')) : '';
    }

    public function isAutoBackupEnabled(): bool
    {
        if (!Schema::hasTable('generals')) {
            return false;
        }

        return DB::table('generals')->where('code', self::GENERAL_AUTO_ENABLED)->value('cvalue') === 'Y';
    }

    public function autoBackupTime(): string
    {
        return $this->autoBackupTimes()[0] ?? '23:00';
    }

    public function autoBackupTimes(): array
    {
        if (!Schema::hasTable('generals')) {
            return ['23:00'];
        }

        $legacy = trim((string) (DB::table('generals')->where('code', self::GENERAL_AUTO_TIME)->value('cvalue') ?? ''));
        $multi = trim((string) (DB::table('generals')->where('code', self::GENERAL_AUTO_TIMES)->value('cvalue') ?? ''));

        $raw = $multi !== '' ? $multi : $legacy;
        $times = collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($time) => trim((string) $time))
            ->filter(fn ($time) => preg_match('/^\d{2}:\d{2}$/', $time) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $times !== [] ? $times : ['23:00'];
    }

    public function saveAutoBackupTimes(string|array $times): array
    {
        if (is_array($times)) {
            $times = implode(',', $times);
        }

        $normalized = collect(preg_split('/[\s,;]+/', (string) $times) ?: [])
            ->map(fn ($time) => trim((string) $time))
            ->filter(fn ($time) => preg_match('/^\d{2}:\d{2}$/', $time) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($normalized === []) {
            $normalized = ['23:00'];
        }

        if (Schema::hasTable('generals')) {
            DB::table('generals')->updateOrInsert(['code' => self::GENERAL_AUTO_TIME], ['cvalue' => $normalized[0]]);
            DB::table('generals')->updateOrInsert(['code' => self::GENERAL_AUTO_TIMES], ['cvalue' => implode(',', $normalized)]);
        }

        return $normalized;
    }

    private function dueBackupTime(): ?string
    {
        $currentTime = date('H:i');
        $runSlots = $this->todaysRunSlots();

        foreach ($this->autoBackupTimes() as $scheduledTime) {
            if ($currentTime >= $scheduledTime && !in_array($scheduledTime, $runSlots, true)) {
                return $scheduledTime;
            }
        }

        return null;
    }

    private function createBackupSet(string $reason, bool $includeProjectSnapshot): array
    {
        $this->ensureZipToolAvailable();

        $stamp = $this->timestamp();
        $created = [];

        $primaryDb = $this->primaryDatabaseName();
        if ($primaryDb !== '') {
            $created[] = $this->createDatabaseZip($primaryDb, 'bk1', $reason, $stamp, 'primary');
            if ($includeProjectSnapshot) {
                $created[] = $this->createProjectZip('bk1', $reason, $stamp, $primaryDb);
            }
        }

        $secondaryDb = $this->secondaryDatabaseName();
        if ($secondaryDb !== '' && strcasecmp($secondaryDb, $primaryDb) !== 0) {
            $created[] = $this->createDatabaseZip($secondaryDb, 'bk2', $reason, $stamp, 'secondary');
        }

        $created = array_values(array_filter($created));

        return [
            'ok' => !empty($created),
            'message' => empty($created)
                ? 'No backup files were created'
                : 'Backup created: ' . implode(', ', array_map(fn ($path) => basename($path), $created)),
            'files' => $created,
        ];
    }

    private function createDatabaseZip(string $database, string $bucket, string $reason, string $stamp, string $label): ?string
    {
        $database = trim($database);
        if ($database === '') {
            return null;
        }

        $dumpPath = $this->temporaryDumpPath($database, $stamp);
        $this->dumpDatabase($database, $dumpPath);

        $zipName = "{$stamp}_{$label}_{$database}_{$reason}.zip";
        $zipPath = $this->backupBucketDir($bucket) . DIRECTORY_SEPARATOR . $zipName;

        $stageDir = $this->temporaryStageDir("db_{$database}_{$stamp}");
        File::copy($dumpPath, $stageDir . DIRECTORY_SEPARATOR . "{$database}.sql");
        File::put($stageDir . DIRECTORY_SEPARATOR . 'meta.json', json_encode([
            'type' => 'database',
            'label' => $label,
            'database' => $database,
            'reason' => $reason,
            'created_at' => date('c'),
            'server_time' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));

        try {
            $this->createZipFromDirectory($stageDir, $zipPath);
        } finally {
            @unlink($dumpPath);
            File::deleteDirectory($stageDir);
        }

        return $zipPath;
    }

    private function createProjectZip(string $bucket, string $reason, string $stamp, string $database): ?string
    {
        $zipName = "{$stamp}_project_{$database}_{$reason}.zip";
        $zipPath = $this->backupBucketDir($bucket) . DIRECTORY_SEPARATOR . $zipName;
        $this->createProjectZipArchive($zipPath, $database, $reason);

        return $zipPath;
    }

    private function createProjectZipArchive(string $zipPath, string $database, string $reason): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create project backup zip: ' . basename($zipPath));
            }

            foreach ($this->projectPathsToBackup() as $path) {
                $absolute = base_path($path);
                if (File::isDirectory($absolute)) {
                    $this->addDirectoryToZip($zip, $absolute, $path);
                    continue;
                }

                if (File::isFile($absolute)) {
                    $zip->addFile($absolute, $path);
                }
            }

            $zip->addFromString('backup-meta.json', json_encode([
                'type' => 'project',
                'database' => $database,
                'reason' => $reason,
                'created_at' => date('c'),
                'server_time' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT));
            $zip->close();
            return;
        }

        $stageDir = $this->temporaryStageDir('project_' . $this->timestamp());
        try {
            foreach ($this->projectPathsToBackup() as $path) {
                $absolute = base_path($path);
                $target = $stageDir . DIRECTORY_SEPARATOR . $path;

                if (File::isDirectory($absolute)) {
                    File::ensureDirectoryExists(dirname($target));
                    File::copyDirectory($absolute, $target);
                    continue;
                }

                if (File::isFile($absolute)) {
                    File::ensureDirectoryExists(dirname($target));
                    File::copy($absolute, $target);
                }
            }

            File::put($stageDir . DIRECTORY_SEPARATOR . 'backup-meta.json', json_encode([
                'type' => 'project',
                'database' => $database,
                'reason' => $reason,
                'created_at' => date('c'),
                'server_time' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT));

            $this->deleteSkippedFilesFromStage($stageDir);
            $this->createZipFromDirectory($stageDir, $zipPath);
        } finally {
            File::deleteDirectory($stageDir);
        }
    }

    private function projectPathsToBackup(): array
    {
        return [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            '.env',
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'phpunit.xml',
            'README.md',
            'vite.config.js',
        ];
    }

    private function addDirectoryToZip(ZipArchive $zip, string $absolutePath, string $relativeRoot): void
    {
        $files = File::allFiles($absolutePath);

        foreach ($files as $file) {
            $fullPath = $file->getPathname();
            $relativePath = $relativeRoot . '/' . str_replace('\\', '/', ltrim(str_replace($absolutePath, '', $fullPath), '\\/'));

            if ($this->shouldSkipProjectFile($relativePath)) {
                continue;
            }

            $zip->addFile($fullPath, $relativePath);
        }
    }

    private function shouldSkipProjectFile(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        return str_contains($relativePath, 'storage/app/backups/')
            || str_contains($relativePath, 'storage/logs/')
            || str_ends_with(strtolower($relativePath), '.zip');
    }

    private function deleteSkippedFilesFromStage(string $stageDir): void
    {
        foreach (File::allFiles($stageDir) as $file) {
            $relative = str_replace('\\', '/', ltrim(str_replace($stageDir, '', $file->getPathname()), '\\/'));
            if ($this->shouldSkipProjectFile($relative)) {
                @unlink($file->getPathname());
            }
        }
    }

    private function dumpDatabase(string $database, string $dumpPath): void
    {
        $dump = $this->mysqldumpPath();
        $dbUser = (string) Config::get('database.connections.mysql.username');
        $dbPass = (string) Config::get('database.connections.mysql.password');
        $dbHost = (string) Config::get('database.connections.mysql.host');
        $dbPort = (string) Config::get('database.connections.mysql.port', '3306');

        $parts = [
            '"' . $dump . '"',
            '-h', escapeshellarg($dbHost),
            '-P', escapeshellarg($dbPort),
            '-u', escapeshellarg($dbUser),
        ];

        if ($dbPass !== '') {
            $parts[] = '-p' . escapeshellarg($dbPass);
        }

        $parts[] = '--single-transaction';
        $parts[] = '--routines';
        $parts[] = '--triggers';
        $parts[] = '--skip-lock-tables';
        $parts[] = escapeshellarg($database);

        $command = implode(' ', $parts) . ' > ' . escapeshellarg($dumpPath) . ' 2>&1';

        exec($command, $output, $status);

        if ($status !== 0 || !File::exists($dumpPath) || filesize($dumpPath) < 50) {
            @unlink($dumpPath);
            throw new RuntimeException('Database backup failed for ' . $database . ': ' . trim(implode("\n", $output)));
        }
    }

    private function temporaryDumpPath(string $database, string $stamp): string
    {
        $tmpDir = $this->backupRoot() . DIRECTORY_SEPARATOR . '_tmp';
        if (!File::isDirectory($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        return $tmpDir . DIRECTORY_SEPARATOR . "{$stamp}_{$database}.sql";
    }

    private function timestamp(): string
    {
        return date('Y-m-d_His') . '_' . substr(str_replace('.', '', (string) microtime(true)), -6);
    }

    private function ensureZipToolAvailable(): void
    {
        if (class_exists(ZipArchive::class) || $this->powershellAvailable()) {
            return;
        }

        throw new RuntimeException('No ZIP tool is available. Enable PHP ZipArchive or PowerShell Compress-Archive.');
    }

    private function mysqldumpPath(): string
    {
        $paths = [
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp/mysql/bin/mysqldump',
            '/usr/bin/mysqldump',
            'mysqldump',
        ];

        foreach ($paths as $path) {
            if ($path === 'mysqldump' || file_exists($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    private function createZipFromDirectory(string $sourceDir, string $zipPath): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create zip: ' . basename($zipPath));
            }

            foreach (File::allFiles($sourceDir) as $file) {
                $relative = str_replace('\\', '/', ltrim(str_replace($sourceDir, '', $file->getPathname()), '\\/'));
                $zip->addFile($file->getPathname(), $relative);
            }

            $zip->close();
            return;
        }

        $this->createZipWithPowerShell($sourceDir, $zipPath);
    }

    private function createZipWithPowerShell(string $sourceDir, string $zipPath): void
    {
        $sourceDir = str_replace("'", "''", $sourceDir);
        $zipPath = str_replace("'", "''", $zipPath);

        $command = "powershell -NoProfile -Command \"Compress-Archive -Path '$sourceDir\\*' -DestinationPath '$zipPath' -Force\"";
        exec($command, $output, $status);

        if ($status !== 0 || !File::exists($zipPath)) {
            throw new RuntimeException('PowerShell ZIP creation failed for ' . basename($zipPath));
        }
    }

    private function powershellAvailable(): bool
    {
        exec('powershell -NoProfile -Command "$PSVersionTable.PSVersion.ToString()"', $output, $status);
        return $status === 0;
    }

    private function temporaryStageDir(string $name): string
    {
        $dir = $this->backupRoot() . DIRECTORY_SEPARATOR . '_stage' . DIRECTORY_SEPARATOR . $name;
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function todaysRunSlots(): array
    {
        if (!Schema::hasTable('generals')) {
            return [];
        }

        $raw = trim((string) (DB::table('generals')->where('code', self::GENERAL_AUTO_RUN_SLOTS)->value('cvalue') ?? ''));
        if ($raw === '') {
            return [];
        }

        [$date, $slots] = array_pad(explode('|', $raw, 2), 2, '');
        if (trim($date) !== date('Y-m-d')) {
            return [];
        }

        return collect(explode(',', $slots))
            ->map(fn ($slot) => trim((string) $slot))
            ->filter(fn ($slot) => preg_match('/^\d{2}:\d{2}$/', $slot) === 1)
            ->unique()
            ->values()
            ->all();
    }

    private function markRunSlot(string $time): string
    {
        $slots = collect($this->todaysRunSlots())
            ->push(trim($time))
            ->filter(fn ($slot) => preg_match('/^\d{2}:\d{2}$/', $slot) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return date('Y-m-d') . '|' . implode(',', $slots);
    }

    private function lockPath(string $name): string
    {
        return $this->backupRoot() . DIRECTORY_SEPARATOR . '_locks' . DIRECTORY_SEPARATOR . $name;
    }

    private function acquireLock(string $name, int $staleAfterSeconds = 900): bool
    {
        $path = $this->lockPath($name);
        File::ensureDirectoryExists(dirname($path));

        if (File::exists($path)) {
            $age = time() - (int) File::lastModified($path);
            if ($age < $staleAfterSeconds) {
                return false;
            }

            @unlink($path);
        }

        return @file_put_contents($path, json_encode([
            'created_at' => date('c'),
            'pid' => function_exists('getmypid') ? getmypid() : null,
        ], JSON_PRETTY_PRINT)) !== false;
    }

    private function releaseLock(string $name): void
    {
        $path = $this->lockPath($name);
        if (File::exists($path)) {
            @unlink($path);
        }
    }
}
