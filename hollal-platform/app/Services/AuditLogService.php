<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Append-only audit trail for sensitive actions.
 * Time: O(1) per write | Space: O(1) per row.
 */
class AuditLogService
{
    public function record(
        string $action,
        ?Model $target = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata !== [] ? $metadata : null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
