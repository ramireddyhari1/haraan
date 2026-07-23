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
use Illuminate\Support\Str;

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

    /**
     * Email + password sign-in for the Android app — the API twin of
     * {@see \App\Http\Controllers\Auth\WebPasswordAuthController}, which the public
     * website already uses. Behaviour is deliberately identical so one credential
     * works on both surfaces: an unknown email signs up on the spot, a known email
     * verifies its password. The only difference is the session — the site logs in
     * via the web guard, the app gets a JWT.
     *
     * Distinct from {@see login()}, which is the stricter admin-style endpoint that
     * never auto-creates an account.
     */
    public function passwordLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'name'     => ['nullable', 'string', 'max:60'],
        ], [
            'password.min' => 'Your password must be at least 6 characters.',
        ]);

        $email = Str::lower(trim($data['email']));

        // Match case-insensitively; emails are stored as entered.
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user !== null) {
            if (!$user->password || !Hash::check($data['password'], (string) $user->password)) {
                // Accounts created via OTP/Google hold a random password, so the nudge
                // to Google matters here — same wording the website gives.
                return response()->json([
                    'error' => 'Incorrect email or password. If you signed up with Google, use "Continue with Google".',
                ], 401);
            }
        } else {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $name = Str::of($email)->before('@')->ucfirst()->value() ?: 'Member';
            }

            $user = User::query()->create([
                'name'     => $name,
                'email'    => $email,
                'password' => Hash::make($data['password']),
                'role'     => 'user',
                'status'   => 'active',
            ]);
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
