<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AdminAction;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminActionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdminAction');
    }

    public function view(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('View:AdminAction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdminAction');
    }

    public function update(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('Update:AdminAction');
    }

    public function delete(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('Delete:AdminAction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdminAction');
    }

    public function restore(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('Restore:AdminAction');
    }

    public function forceDelete(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('ForceDelete:AdminAction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdminAction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdminAction');
    }

    public function replicate(AuthUser $authUser, AdminAction $adminAction): bool
    {
        return $authUser->can('Replicate:AdminAction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdminAction');
    }

}