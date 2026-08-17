<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(?User $user, string $action, string $module, ?int $recordId = null, ?string $description = null, ?Request $request = null): AuditLog
    {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 1000),
            'created_at' => ManilaTime::now(),
        ]);
    }
}
