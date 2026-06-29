<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payout;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayoutPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payout');
    }

    public function view(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('View:Payout');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payout');
    }

    public function update(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('Update:Payout');
    }

    public function delete(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('Delete:Payout');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Payout');
    }

    public function restore(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('Restore:Payout');
    }

    public function forceDelete(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('ForceDelete:Payout');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Payout');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Payout');
    }

    public function replicate(AuthUser $authUser, Payout $payout): bool
    {
        return $authUser->can('Replicate:Payout');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Payout');
    }

}