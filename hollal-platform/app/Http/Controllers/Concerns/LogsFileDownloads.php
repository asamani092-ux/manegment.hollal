<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait LogsFileDownloads
{
    protected function auditFileDownload(string $fileType, Model $target): void
    {
        app(AuditLogService::class)->record('file.download', $target, [
            'file_type' => $fileType,
        ]);
    }
}
