<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FeatureFlag;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeatureFlagPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FeatureFlag');
    }

    public function view(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('View:FeatureFlag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FeatureFlag');
    }

    public function update(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('Update:FeatureFlag');
    }

    public function delete(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('Delete:FeatureFlag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FeatureFlag');
    }

    public function restore(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('Restore:FeatureFlag');
    }

    public function forceDelete(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('ForceDelete:FeatureFlag');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FeatureFlag');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FeatureFlag');
    }

    public function replicate(AuthUser $authUser, FeatureFlag $featureFlag): bool
    {
        return $authUser->can('Replicate:FeatureFlag');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FeatureFlag');
    }

}