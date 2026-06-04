<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('events.view') || $user->can('events.edit') || $this->isLegacyAdmin($user);
    }

    public function create(User $user): bool
    {
        return $user->can('events.create') || $this->isLegacyAdmin($user);
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->can('events.edit') || $this->isLegacyAdmin($user)) {
            return true;
        }

        return (int) $event->partner_id === (int) $user->id && $user->can('events.edit.own');
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->can('events.delete') || $this->isLegacyAdmin($user)) {
            return true;
        }

        return (int) $event->partner_id === (int) $user->id && $user->can('events.delete.own');
    }

    private function isLegacyAdmin(User $user): bool
    {
        return in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN'], true);
    }
}
