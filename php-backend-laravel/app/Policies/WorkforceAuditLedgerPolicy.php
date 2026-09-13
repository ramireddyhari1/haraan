<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class WorkforceAuditLedgerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN', 'OPS'], true);
    }

    public function view(User $user, WorkforceAuditLedger $ledger): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Audit ledger is strictly append-only via WorkforceAuditService.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Immutable audit ledger cannot be modified by any user.
     */
    public function update(User $user, WorkforceAuditLedger $ledger): bool
    {
        return false;
    }

    /**
     * Immutable audit ledger cannot be deleted by any user.
     */
    public function delete(User $user, WorkforceAuditLedger $ledger): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
