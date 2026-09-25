<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AdminAction;

trait LogsAdminActions
{
    /** Goes through AdminAction::log so meta is redacted and stored as JSON once (not double-encoded). */
    private function logAction(string $action, array $meta = []): void
    {
        AdminAction::log($action, $meta);
    }
}
