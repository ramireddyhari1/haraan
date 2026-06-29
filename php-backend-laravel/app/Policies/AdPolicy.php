<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Ad;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ad');
    }

    public function view(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('View:Ad');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ad');
    }

    public function update(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('Update:Ad');
    }

    public function delete(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('Delete:Ad');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ad');
    }

    public function restore(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('Restore:Ad');
    }

    public function forceDelete(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('ForceDelete:Ad');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Ad');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Ad');
    }

    public function replicate(AuthUser $authUser, Ad $ad): bool
    {
        return $authUser->can('Replicate:Ad');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Ad');
    }

}