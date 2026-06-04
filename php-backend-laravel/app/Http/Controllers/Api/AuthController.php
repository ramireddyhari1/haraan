<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Handles user registration, login, logout and profile retrieval.
 */
final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name'     => $request->validated('name'),
            'email'    => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role'     => 'USER',
            'status'   => 'ACTIVE',
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'token'   => $this->issueToken($user),
            'user'    => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($user === null || !Hash::check($request->validated('password'), (string) $user->password)) {
            return response()->json(['error' => 'Invalid email or password'], 400);
        }

        return response()->json([
            'message' => 'Login successful',
            'token'   => $this->issueToken($user),
            'user'    => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        return response()->json(['message' => 'Logout successful']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json(['user' => new UserResource($user)]);
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /** Issue a JWT for the given user. */
    private function issueToken(User $user): string
    {
        return JwtService::issue([
            'sub'   => $user->id,
            'email' => $user->email,
            'role'  => $user->role,
        ], (string) config('app.jwt_secret'));
    }
}
