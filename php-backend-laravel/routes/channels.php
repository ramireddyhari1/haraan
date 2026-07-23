<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The "content" channel is public — content-invalidation signals (feature
| flags, home layout, branding, translations) carry no sensitive data, just a
| "this changed, refetch" nudge. District-scoped channels are authorized below
| for future per-district pushes.
|
*/

Broadcast::channel('district.{districtId}', function (User $user, int $districtId): bool {
    // A user may listen to their own district's channel; super-admins to any.
    return $user->isSuperAdmin() || (int) ($user->organization_id ?? 0) === $districtId;
});
