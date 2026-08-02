<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PartnerAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderByDesc('created_at');
        if ($request->filled('role')) {
            $query->where('role', (string) $request->query('role'));
        }

        return response()->json(['data' => $query->paginate((int) $request->query('limit', 20))]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json(['data' => $user]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->fill($request->only(['name', 'email', 'phone', 'avatar']));

        if ($request->filled('password')) {
            $user->password = Hash::make((string) $request->string('password'));
        }

        $user->save();

        return response()->json(['message' => 'User updated', 'data' => $user]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $role = strtoupper((string) $request->input('role', 'USER'));
        $user->role = $role;
        $user->save();

        return response()->json(['message' => 'Role updated', 'data' => $user]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $status = strtoupper((string) $request->input('status', 'ACTIVE'));
        $user->status = $status;
        $user->save();

        return response()->json(['message' => 'Status updated', 'data' => $user]);
    }

    public function partners(): JsonResponse
    {
        $partners = User::query()->where('role', 'PARTNER')->orderByDesc('created_at')->get();
        return response()->json(['data' => $partners]);
    }

    /**
     * An email that already exists is usually the same human — venue owners and event
     * hosts are typically Haraan members before they list anything — so upgrade that
     * account in place rather than failing on the unique index. See
     * {@see \App\Support\PartnerAccountResolver} for why one email can only be one row.
     */
    public function createPartner(Request $request): JsonResponse
    {
        try {
            [$partner, $upgraded] = PartnerAccountResolver::upgradeOrCreate([
                'name' => (string) $request->string('name'),
                'email' => (string) $request->string('email'),
                'partner_type' => $request->input('partnerType'),
                'event_host_id' => $request->input('eventHostId'),
                'status' => 'ACTIVE',
            ], (string) $request->input('password', 'partner123'));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $upgraded ? 'Existing member upgraded to a partner' : 'Partner created',
            'upgraded' => $upgraded,
            'data' => $partner,
        ], $upgraded ? 200 : 201);
    }
}
