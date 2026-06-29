<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Venue;
use Illuminate\Auth\Access\HandlesAuthorization;

class VenuePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Venue');
    }

    public function view(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('View:Venue');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Venue');
    }

    public function update(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('Update:Venue');
    }

    public function delete(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('Delete:Venue');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Venue');
    }

    public function restore(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('Restore:Venue');
    }

    public function forceDelete(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('ForceDelete:Venue');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Venue');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Venue');
    }

    public function replicate(AuthUser $authUser, Venue $venue): bool
    {
        return $authUser->can('Replicate:Venue');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Venue');
    }

}