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
                    $query = User::query()->orderByDesc('created_at');
                } else {
                $orgIds = DB::table('user_organization_map')->where('user_id', $user->id)->pluck('organization_id')->toArray();
                if (empty($orgIds)) {
                    return response()->json(['data' => []]);
                }

                $query = User::join('user_organization_map as uom', 'uom.user_id', '=', 'users.id')
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
                $data = $query->paginate($limit);
                return response()->json(['data' => $data]);
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
        $this->logAction('user.assign_role', ['user_id' => $user->id, 'role' => $role]);
        return response()->json(['message' => 'Role assigned', 'data' => $user]);
    }
}
