<?php

namespace App\Http\Controllers;

use App\Support\DatabaseBackupManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupManager $backupManager
    ) {
    }

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $backups = $this->backupManager->listBackups();

        // Auto-backup settings from generals table
        $autoEnabled = $this->backupManager->isAutoBackupEnabled();
        $autoTime = $this->backupManager->autoBackupTime();
        $autoTimes = implode(', ', $this->backupManager->autoBackupTimes());
        $localDiskPath = $this->backupManager->localDiskBackupPath();

        $dbName = $this->backupManager->primaryDatabaseName();
        $secondaryDbName = $this->backupManager->secondaryDatabaseName();
        $dbSize = $this->getDbSize();
        $mode = (string) $request->query('mode', '');
        $localMode = $mode === 'local';

        return view('backup.index', compact('backups', 'autoEnabled', 'autoTime', 'autoTimes', 'localDiskPath', 'dbName', 'secondaryDbName', 'dbSize', 'localMode'));
    }

    public function runBackup(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->backupManager->runManualBackup('manual');
            return response()->json($this->attachDownloadLinks($result));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
        }
    }

    public function download(Request $request, string $file)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $path = $this->backupManager->backupRoot() . '/' . str_replace(['..\\', '../'], '', str_replace('\\', '/', trim($file)));
        if (!file_exists($path)) abort(404, 'Backup file not found');

        return response()->download($path);
    }

    public function delete(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $file = (string) $request->input('file', '');

        if ($this->backupManager->deleteBackup($file)) {
            return response()->json(['ok' => true, 'message' => "Deleted: {$file}"]);
        }

        return response()->json(['ok' => false, 'message' => 'File not found']);
    }

    public function runLocalDiskBackup(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $path = trim((string) $request->input('path', ''));

        try {
            $result = $this->backupManager->runLocalDiskBackup($path);
            return response()->json($this->attachDownloadLinks($result), ($result['ok'] ?? false) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Local disk backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function runBrowserLocalDiskBackup(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->backupManager->runLocalDiskBackupSet('local-disk');
            return response()->json($this->attachDownloadLinks($result), ($result['ok'] ?? false) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Browser local disk backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function browseLocalDiskPath(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $path = trim((string) $request->input('path', ''));

        try {
            $result = $this->backupManager->browseLocalDiskPath($path);
            return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Browse folder failed: ' . $e->getMessage()], 500);
        }
    }

    public function saveLocalDiskPath(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $path = trim((string) $request->input('path', ''));
        if ($path === '') {
            return response()->json(['ok' => false, 'message' => 'Please enter a local disk folder path.'], 422);
        }

        $normalized = $this->backupManager->saveLocalDiskBackupPath($path);
        if ($normalized === '') {
            return response()->json(['ok' => false, 'message' => 'Please enter a valid local disk folder path.'], 422);
        }

        return response()->json([
            'ok' => true,
            'path' => $normalized,
            'message' => 'Default local disk path saved: ' . $normalized,
        ]);
    }

    public function saveAutoSettings(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $enabled = $request->input('enabled') ? 'Y' : 'N';
        $times = $this->backupManager->saveAutoBackupTimes((string) $request->input('times', (string) $request->input('time', '23:00')));

        if ($this->hasTable('generals')) {
            DB::table('generals')->updateOrInsert(['code' => 'AUTOBACKUP'], ['cvalue' => $enabled]);
        }

        return response()->json([
            'ok' => true,
            'times' => $times,
            'message' => "Auto backup " . ($enabled === 'Y' ? 'enabled at ' . implode(', ', $times) : 'disabled'),
        ]);
    }

    private function getDbSize(): string
    {
        $dbName = config('database.connections.mysql.database');
        $result = DB::select("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables WHERE table_schema = ?
        ", [$dbName]);
        return ($result[0]->size_mb ?? '0') . ' MB';
    }

    private function attachDownloadLinks(array $result): array
    {
        $files = $result['files'] ?? [];
        if (!is_array($files) || $files === []) {
            $result['downloads'] = [];
            return $result;
        }

        $root = str_replace('\\', '/', $this->backupManager->backupRoot());
        $downloads = [];

        foreach ($files as $file) {
            $path = str_replace('\\', '/', (string) $file);
            $relative = ltrim(str_replace($root, '', $path), '/');
            if ($relative === '') {
                continue;
            }

            $downloads[] = [
                'name' => basename($relative),
                'relative_path' => $relative,
                'url' => url('/backup/download/' . collect(explode('/', $relative))
                    ->map(fn ($segment) => rawurlencode($segment))
                    ->implode('/')),
            ];
        }

        $result['downloads'] = $downloads;
        return $result;
    }
}
