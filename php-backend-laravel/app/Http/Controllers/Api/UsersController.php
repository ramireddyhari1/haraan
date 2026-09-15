<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\JwtService;
use App\Support\PartnerAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Account administration. Every route here is behind auth.jwt + auth.admin (see
 * routes/api.php) — this controller reads and rewrites OTHER people's accounts, so the
 * route gate is the first line and the checks below are the second:
 *
 *  - roles come from a fixed list, never free text;
 *  - only a full ADMIN may grant or remove ADMIN / COADMIN (a co-admin cannot mint an
 *    admin, including themselves);
 *  - nobody changes their own role or status through this API, so an admin can't lock
 *    the last admin out by accident;
 *  - an admin account's credentials can only be changed by a full ADMIN.
 */
final class UsersController extends Controller
{
    /** Every role this API may assign. Anything else is a validation error. */
    public const ASSIGNABLE_ROLES = ['USER', 'PARTNER', 'FINANCE', 'MARKETING', 'OPS', 'EMPLOYEE', 'COADMIN', 'ADMIN'];

    /** Roles only a full ADMIN may grant or take away. */
    private const PRIVILEGED_ROLES = ['ADMIN', 'COADMIN'];

    public const STATUSES = ['ACTIVE', 'SUSPENDED', 'INACTIVE'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->orderByDesc('created_at');
        if (isset($data['role'])) {
            $query->whereRaw('upper(role) = ?', [strtoupper($data['role'])]);
        }

        return response()->json(['data' => $query->paginate((int) ($data['limit'] ?? 20))]);
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

        $actor = $request->user();
        if ($refusal = $this->refuseTouchingPrivileged($actor, $user)) {
            return $refusal;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'password' => ['sometimes', 'string', 'min:10', 'max:128'],
        ]);

        $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'phone', 'avatar'])));

        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // A reset password must end every session the old one opened.
        if (isset($data['password'])) {
            JwtService::revokeAllFor($user);
        }

        return response()->json(['message' => 'User updated', 'data' => $user]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(array_merge(self::ASSIGNABLE_ROLES, array_map('strtolower', self::ASSIGNABLE_ROLES)))],
        ]);
        $role = strtoupper($data['role']);
        $actor = $request->user();

        if ((int) $actor->id === (int) $user->id) {
            return response()->json(['error' => 'You cannot change your own role.'], 403);
        }

        $touchesPrivileged = in_array($role, self::PRIVILEGED_ROLES, true)
            || in_array(strtoupper((string) $user->role), self::PRIVILEGED_ROLES, true);
        if ($touchesPrivileged && ! $this->isFullAdmin($actor)) {
            return response()->json(['error' => 'Only an administrator can grant or remove admin roles.'], 403);
        }

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

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_merge(self::STATUSES, array_map('strtolower', self::STATUSES)))],
        ]);
        $actor = $request->user();

        if ((int) $actor->id === (int) $user->id) {
            return response()->json(['error' => 'You cannot change your own status.'], 403);
        }
        if ($refusal = $this->refuseTouchingPrivileged($actor, $user)) {
            return $refusal;
        }

        $user->status = strtoupper($data['status']);
        $user->save();

        return response()->json(['message' => 'Status updated', 'data' => $user]);
    }

    public function partners(): JsonResponse
    {
        $partners = User::query()->whereRaw('upper(role) = ?', ['PARTNER'])->orderByDesc('created_at')->get();
        return response()->json(['data' => $partners]);
    }

    /**
     * An email that already exists is usually the same human — venue owners and event
     * hosts are typically Haraan members before they list anything — so upgrade that
     * account in place rather than failing on the unique index. See
     * {@see \App\Support\PartnerAccountResolver} for why one email can only be one row.
     *
     * No default password: a new partner without one gets a random secret (they sign in
     * by OTP or reset), and an existing member's password is never overwritten unless
     * the admin deliberately supplies one.
     */
    public function createPartner(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'partnerType' => ['nullable', 'string', 'max:40'],
            'eventHostId' => ['nullable'],
            'password' => ['nullable', 'string', 'min:10', 'max:128'],
        ]);

        try {
            [$partner, $upgraded] = PartnerAccountResolver::upgradeOrCreate([
                'name' => $data['name'],
                'email' => $data['email'],
                'partner_type' => $data['partnerType'] ?? null,
                'event_host_id' => $data['eventHostId'] ?? null,
                'status' => 'ACTIVE',
            ], $data['password'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $upgraded ? 'Existing member upgraded to a partner' : 'Partner created',
            'upgraded' => $upgraded,
            'data' => $partner,
        ], $upgraded ? 200 : 201);
    }

    private function isFullAdmin(?User $actor): bool
    {
        return $actor !== null && $actor->hasRoleEither(['ADMIN']);
    }

    /** A co-admin may not rewrite an admin or co-admin account. */
    private function refuseTouchingPrivileged(?User $actor, User $target): ?JsonResponse
    {
        $targetPrivileged = in_array(strtoupper((string) $target->role), self::PRIVILEGED_ROLES, true);
        if ($targetPrivileged && ! $this->isFullAdmin($actor) && (int) $actor?->id !== (int) $target->id) {
            return response()->json(['error' => 'Only an administrator can change an admin account.'], 403);
        }
        return null;
    }
}
