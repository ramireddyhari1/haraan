<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

    public function createPartner(Request $request): JsonResponse
    {
        $partner = User::query()->create([
            'name' => (string) $request->string('name'),
            'email' => (string) $request->string('email'),
            'password' => Hash::make((string) $request->input('password', 'partner123')),
            'role' => 'PARTNER',
            'status' => 'ACTIVE',
            'partner_type' => $request->input('partnerType'),
            'event_host_id' => $request->input('eventHostId'),
        ]);

        return response()->json(['message' => 'Partner created', 'data' => $partner], 201);
    }
}
