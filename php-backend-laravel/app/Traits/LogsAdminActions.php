<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AdminAction;
use Illuminate\Support\Facades\Auth;

trait LogsAdminActions
{
    private function logAction(string $action, array $meta = []): void
    {
        $user = Auth::user();
        AdminAction::create([
            'user_id' => $user?->id,
            'action' => $action,
            'meta' => json_encode($meta),
            'ip' => request()->ip(),
        ]);
    }
}
