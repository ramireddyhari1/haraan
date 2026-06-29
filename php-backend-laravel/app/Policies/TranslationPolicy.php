<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Translation;
use Illuminate\Auth\Access\HandlesAuthorization;

class TranslationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Translation');
    }

    public function view(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('View:Translation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Translation');
    }

    public function update(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('Update:Translation');
    }

    public function delete(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('Delete:Translation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Translation');
    }

    public function restore(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('Restore:Translation');
    }

    public function forceDelete(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('ForceDelete:Translation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Translation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Translation');
    }

    public function replicate(AuthUser $authUser, Translation $translation): bool
    {
        return $authUser->can('Replicate:Translation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Translation');
    }

}