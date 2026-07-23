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

                $users->each(function ($u) use ($userBookingsData, $partnerRevenueData) {
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
        $user->status = 'SUSPENDED';
        $user->save();
        $this->logAction('user.suspend', ['id' => $user->id]);
        return response()->json(['message' => 'User suspended', 'data' => $user]);
    }

    public function reactivate(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->status = 'ACTIVE';
        $user->save();
        $this->logAction('user.reactivate', ['id' => $user->id]);
        return response()->json(['message' => 'User reactivated', 'data' => $user]);
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $request->validate(['role' => ['required','string']]);
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $role = $request->input('role');
        $user->syncRoles([$role]);
        $user->role = strtoupper($role);
        $user->save();
        $this->logAction('user.assign_role', ['user_id' => $user->id, 'role' => $role]);
        return response()->json(['message' => 'Role assigned', 'data' => $user]);
    }
}
