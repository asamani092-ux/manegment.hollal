<?php

namespace App\Services;

use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * 11-B1 — backup status and manual trigger.
 *
 * The database file is copied to the private disk; the run timestamp lives in
 * platform_settings so the settings screen reads it live.
 */
class BackupService
{
    public function run(?User $actor = null): string
    {
        $path = 'backups/backup-'.now()->format('Ymd-His').'.sqlite';
        $source = config('database.connections.'.config('database.default').'.database');

        Storage::disk('local')->put(
            $path,
            (is_string($source) && is_file($source)) ? (string) file_get_contents($source) : ''
        );

        Setting::set('backup.last_run_at', now()->toDateTimeString(), $actor);

        app(AuditLogService::class)->record(
            action: 'backup.created',
            metadata: ['path' => $path],
            actor: $actor,
        );

        return $path;
    }

    /**
     * @return array{last_run_at: ?string, retention_days: int, files: int}
     */
    public function status(): array
    {
        return [
            'last_run_at' => Setting::get('backup.last_run_at'),
            'retention_days' => (int) Setting::get('backup.retention_days', 30),
            'files' => count(Storage::disk('local')->files('backups')),
        ];
    }
}
