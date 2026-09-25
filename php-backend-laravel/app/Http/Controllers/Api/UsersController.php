<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAdminResource;
use App\Models\AdminAction;
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
            $query->where('role', strtoupper($data['role']));
        }

        $paginator = $query->paginate((int) ($data['limit'] ?? 20));
        $paginator->getCollection()->transform(function (User $u) use ($request) {
            return (new UserAdminResource($u))->toArray($request);
        });

        return response()->json(['data' => $paginator]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $actor = $request->user();
        $canViewPii = $actor !== null && (
            $actor->isSuperAdmin()
            || (method_exists($actor, 'can') && $actor->can('users.pii.view'))
        );

        if ($canViewPii && (int) $actor->id !== (int) $user->id) {
            AdminAction::log('user.pii_viewed', [
                'user_id' => $user->id,
                'viewed_fields' => ['phone', 'email', 'date_of_birth'],
            ], $user);
        }

        return response()->json(['data' => new UserAdminResource($user)]);
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

        return response()->json(['message' => 'User updated', 'data' => new UserAdminResource($user)]);
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

        $previous = strtoupper((string) $user->role);
        $user->role = $role;
        $user->save();

        // A role is baked into the JWT payload, so a live token keeps asserting the OLD
        // role until it expires. Revoking forces a fresh sign-in at the new privilege
        // level — which matters most in the direction that removes access.
        JwtService::revokeAllFor($user);

        // JWT guard, so AuditsAdminChanges does not fire — log the escalation explicitly.
        AdminAction::log('user.role_changed', [
            'from' => $previous,
            'to' => $role,
        ], $user);

        return response()->json(['message' => 'Role updated', 'data' => new UserAdminResource($user)]);
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

        $previous = strtoupper(trim((string) $user->status));
        $user->status = strtoupper($data['status']);
        $user->save();

        // Suspending someone has to end the sessions they already hold. Their JWT is
        // stateless with a 7-day life, so without this the suspension would not take
        // effect until the token happened to expire.
        if (! $user->isAccountActive()) {
            JwtService::revokeAllFor($user);
        }

        // This controller authenticates with a JWT, so AuditsAdminChanges (which only
        // fires for the `web` guard) never sees it. Status changes made through the API
        // were invisible in the audit log; log them explicitly.
        AdminAction::log('user.status_changed', [
            'from' => $previous,
            'to' => $user->status,
        ], $user);

        return response()->json(['message' => 'Status updated', 'data' => new UserAdminResource($user)]);
    }

    public function partners(Request $request): JsonResponse
    {
        $partners = User::query()->where('role', 'PARTNER')->orderByDesc('created_at')->get();
        return response()->json([
            'data' => $partners->map(fn (User $p) => (new UserAdminResource($p))->toArray($request)),
        ]);
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
            'data' => new UserAdminResource($partner),
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
