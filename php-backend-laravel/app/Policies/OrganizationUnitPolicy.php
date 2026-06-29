<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrganizationUnit;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationUnitPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrganizationUnit');
    }

    public function view(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('View:OrganizationUnit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrganizationUnit');
    }

    public function update(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('Update:OrganizationUnit');
    }

    public function delete(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('Delete:OrganizationUnit');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OrganizationUnit');
    }

    public function restore(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('Restore:OrganizationUnit');
    }

    public function forceDelete(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('ForceDelete:OrganizationUnit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrganizationUnit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrganizationUnit');
    }

    public function replicate(AuthUser $authUser, OrganizationUnit $organizationUnit): bool
    {
        return $authUser->can('Replicate:OrganizationUnit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrganizationUnit');
    }

}