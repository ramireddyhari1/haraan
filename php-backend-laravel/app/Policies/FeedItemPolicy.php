<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FeedItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeedItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FeedItem');
    }

    public function view(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('View:FeedItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FeedItem');
    }

    public function update(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('Update:FeedItem');
    }

    public function delete(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('Delete:FeedItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FeedItem');
    }

    public function restore(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('Restore:FeedItem');
    }

    public function forceDelete(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('ForceDelete:FeedItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FeedItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FeedItem');
    }

    public function replicate(AuthUser $authUser, FeedItem $feedItem): bool
    {
        return $authUser->can('Replicate:FeedItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FeedItem');
    }

}