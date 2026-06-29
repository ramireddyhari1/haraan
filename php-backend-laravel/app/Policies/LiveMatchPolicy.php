<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LiveMatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class LiveMatchPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LiveMatch');
    }

    public function view(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('View:LiveMatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LiveMatch');
    }

    public function update(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('Update:LiveMatch');
    }

    public function delete(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('Delete:LiveMatch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LiveMatch');
    }

    public function restore(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('Restore:LiveMatch');
    }

    public function forceDelete(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('ForceDelete:LiveMatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LiveMatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LiveMatch');
    }

    public function replicate(AuthUser $authUser, LiveMatch $liveMatch): bool
    {
        return $authUser->can('Replicate:LiveMatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LiveMatch');
    }

}