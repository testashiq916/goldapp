<?php

use App\Support\DatabaseBackupManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:auto-run', function (DatabaseBackupManager $backupManager) {
    $result = $backupManager->runScheduledBackupsIfDue();

    if (($result['skipped'] ?? false) === true) {
        $this->comment((string) ($result['message'] ?? 'Skipped'));
        return;
    }

    if (($result['ok'] ?? false) !== true) {
        $this->error((string) ($result['message'] ?? 'Backup failed'));
        return;
    }

    $this->info((string) ($result['message'] ?? 'Backup completed'));
})->purpose('Run automatic database backup when the saved backup time is due');

Schedule::command('backup:auto-run')->everyMinute()->withoutOverlapping();
