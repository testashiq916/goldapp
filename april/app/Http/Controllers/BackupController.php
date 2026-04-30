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

        $dbName = $this->backupManager->primaryDatabaseName();
        $secondaryDbName = $this->backupManager->secondaryDatabaseName();
        $dbSize = $this->getDbSize();

        return view('backup.index', compact('backups', 'autoEnabled', 'autoTime', 'autoTimes', 'dbName', 'secondaryDbName', 'dbSize'));
    }

    public function runBackup(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->backupManager->runManualBackup('manual');
            return response()->json($result);
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
}
