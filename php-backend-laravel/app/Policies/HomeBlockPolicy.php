<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HomeBlock;
use Illuminate\Auth\Access\HandlesAuthorization;

class HomeBlockPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HomeBlock');
    }

    public function view(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('View:HomeBlock');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HomeBlock');
    }

    public function update(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('Update:HomeBlock');
    }

    public function delete(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('Delete:HomeBlock');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:HomeBlock');
    }

    public function restore(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('Restore:HomeBlock');
    }

    public function forceDelete(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('ForceDelete:HomeBlock');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HomeBlock');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HomeBlock');
    }

    public function replicate(AuthUser $authUser, HomeBlock $homeBlock): bool
    {
        return $authUser->can('Replicate:HomeBlock');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HomeBlock');
    }

}