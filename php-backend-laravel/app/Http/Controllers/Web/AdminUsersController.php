<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Traits\LogsAdminActions;

final class AdminUsersController extends Controller
{
    use LogsAdminActions;
    public function indexJson(Request $request): JsonResponse
    {
            $user = Auth::user();

            $q = $request->query('q');
            if ($user->hasRole('SUPER ADMIN') || $user->can('users.view.all')) {
                    $query = User::with('organizations')->orderByDesc('created_at');
                } else {
                $orgIds = DB::table('user_organization_map')->where('user_id', $user->id)->pluck('organization_id')->toArray();
                if (empty($orgIds)) {
                    return response()->json(['data' => []]);
                }

                $query = User::with('organizations')
                    ->join('user_organization_map as uom', 'uom.user_id', '=', 'users.id')
                    ->whereIn('uom.organization_id', $orgIds)
                    ->select('users.*')
                    ->orderByDesc('users.created_at');
            }

                if ($request->filled('role')) {
                    $query->where('role', $request->query('role'));
                }

                if ($q) {
                    $query->where(function($r) use ($q) {
                        $r->where('users.name', 'like', "%{$q}%")->orWhere('users.email', 'like', "%{$q}%");
                    });
                }

                $limit = (int) $request->query('limit', 50);
                $paginator = $query->paginate($limit);
                
                $users = $paginator->getCollection();
                $userIds = $users->pluck('id')->toArray();

                // 1. Fetch bookings count and total amount spent for these users
                $userBookingsData = DB::table('bookings')
                    ->whereIn('user_id', $userIds)
                    ->whereIn('status', ['PAID', 'CONFIRMED', 'paid', 'confirmed'])
                    ->select('user_id', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as total'))
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id');

                // 2. Fetch revenue collected by these users if they are partners (events.bookings)
                $partnerRevenueData = DB::table('bookings')
                    ->join('events', 'events.id', '=', 'bookings.event_id')
                    ->whereIn('events.partner_id', $userIds)
                    ->whereIn('bookings.status', ['PAID', 'CONFIRMED', 'paid', 'confirmed'])
                    ->select('events.partner_id', DB::raw('sum(bookings.total_amount) as total'))
                    ->groupBy('events.partner_id')
                    ->get()
                    ->keyBy('partner_id');

                $actor = Auth::user();
                $canViewPii = $actor !== null && (
                    $actor->isSuperAdmin()
                    || (method_exists($actor, 'can') && $actor->can('users.pii.view'))
                );

                $users->each(function ($u) use ($userBookingsData, $partnerRevenueData, $canViewPii) {
                    if (! $canViewPii) {
                        $u->phone = \App\Http\Resources\UserAdminResource::maskPhone($u->phone);
                        $u->email = \App\Http\Resources\UserAdminResource::maskEmail($u->email);
                    }
                    $u->organizations_list = $u->organizations->pluck('name')->implode(', ') ?: '—';
                    
                    // Bookings count
                    $bData = $userBookingsData->get($u->id);
                    $u->bookings_count = $bData ? (int)$bData->count : 0;
                    
                    // Revenue calculation
                    if (in_array(strtoupper((string)$u->role), ['PARTNER', 'ORGANIZER'], true)) {
                        $pData = $partnerRevenueData->get($u->id);
                        $u->revenue = $pData ? (float)$pData->total : 0.0;
                    } else {
                        $u->revenue = $bData ? (float)$bData->total : 0.0;
                    }
                    
                    // Trust score
                    $u->trust_score_value = $u->trust_score ?? 100;
                    
                    // Risk assessment
                    if ($u->status === 'SUSPENDED') {
                        $u->risk_level = 'High (Suspended)';
                    } elseif ($u->trust_score_value < 50) {
                        $u->risk_level = 'High';
                    } elseif ($u->trust_score_value < 80) {
                        $u->risk_level = 'Medium';
                    } else {
                        $u->risk_level = 'Low';
                    }
                    
                    // Last active
                    $u->last_active_human = $u->updated_at ? $u->updated_at->diffForHumans() : '—';
                });

                return response()->json(['data' => $paginator]);
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        if ($refusal = $this->refuseSelfOrPrivileged($request, $user)) {
            return $refusal;
        }
        $user->status = 'SUSPENDED';
        $user->save();
        // A stateless JWT outlives the decision to suspend by up to seven days, so the
        // suspension does not take effect until the token is revoked.
        \App\Support\JwtService::revokeAllFor($user);
        $this->logAction('user.suspend', ['id' => $user->id]);
        return response()->json(['message' => 'User suspended', 'data' => $user]);
    }

    public function reactivate(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        if ($refusal = $this->refuseSelfOrPrivileged($request, $user)) {
            return $refusal;
        }
        // An erased account (AccountEraser leaves status 'deleted') must never be
        // reactivated — its identity is gone, so "reactivating" would hand someone an
        // anonymised shell that still owns real bookings.
        if (strtoupper((string) $user->status) === 'DELETED') {
            return response()->json(['error' => 'This account was erased and cannot be reactivated.'], 422);
        }
        $user->status = 'ACTIVE';
        $user->save();
        $this->logAction('user.reactivate', ['id' => $user->id]);
        return response()->json(['message' => 'User reactivated', 'data' => $user]);
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        // `['required','string']` accepted ANY string, so this endpoint could mint a
        // role that does not exist, or grant ADMIN to anyone — including the caller's
        // own account. The allow-list and the guards below mirror the rules the JSON API
        // already enforces in Api\UsersController; the two paths write the same column
        // and must not disagree about who may do what.
        $request->validate([
            'role' => ['required', 'string', \Illuminate\Validation\Rule::in(
                \App\Http\Controllers\Api\UsersController::ASSIGNABLE_ROLES
            )],
        ]);
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $role = strtoupper($request->input('role'));
        $actor = Auth::user();

        if ((int) $actor->id === (int) $user->id) {
            return response()->json(['error' => 'You cannot change your own role.'], 403);
        }

        // Granting OR removing ADMIN/COADMIN is a full-admin decision. Without this a
        // co-admin could promote themselves via a second account, or demote the last
        // real admin.
        $privileged = ['ADMIN', 'COADMIN'];
        $touchesPrivileged = in_array($role, $privileged, true)
            || in_array(strtoupper((string) $user->role), $privileged, true);
        if ($touchesPrivileged && strtoupper((string) $actor->role) !== 'ADMIN') {
            return response()->json(['error' => 'Only an administrator can grant or remove admin roles.'], 403);
        }

        $previous = strtoupper((string) $user->role);
        $user->syncRoles([$role]);
        $user->role = $role;
        $user->save();

        // The old role is baked into any live JWT; revoke so it cannot outlive the change.
        \App\Support\JwtService::revokeAllFor($user);

        $this->logAction('user.assign_role', ['user_id' => $user->id, 'from' => $previous, 'to' => $role]);
        return response()->json(['message' => 'Role assigned', 'data' => $user]);
    }

    /**
     * Shared guard for the account-state endpoints: nobody may act on their own account
     * here, and only a full ADMIN may act on another privileged account.
     */
    private function refuseSelfOrPrivileged(Request $request, User $user): ?JsonResponse
    {
        $actor = Auth::user();

        if ((int) $actor->id === (int) $user->id) {
            return response()->json(['error' => 'You cannot change your own account status.'], 403);
        }

        $targetIsPrivileged = in_array(strtoupper((string) $user->role), ['ADMIN', 'COADMIN'], true);
        if ($targetIsPrivileged && strtoupper((string) $actor->role) !== 'ADMIN') {
            return response()->json(['error' => 'Only an administrator can act on an admin account.'], 403);
        }

        return null;
    }
}
