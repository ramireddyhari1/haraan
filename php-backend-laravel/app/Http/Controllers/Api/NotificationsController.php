<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The app's bell inbox. Lists the delivered notifications that target the
 * signed-in user (global + their segment), with per-user read state, and lets
 * the app mark them read. Admins compose/send from the Filament "Notifications"
 * resource; delivery to open apps rides the existing Reverb `notifications` signal.
 */
final class NotificationsController extends Controller
{
    /**
     * GET /api/notifications
     * The user's inbox (newest first) + unread count.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $readIds = NotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('notification_id')
            ->all();
        $readSet = array_flip($readIds);

        $items = Notification::query()
            ->sent()
            ->forUser($user)
            ->limit(50)
            ->get()
            ->map(fn (Notification $n): array => [
                'id'         => $n->id,
                'title'      => $n->title,
                'body'       => $n->body,
                'image_url'  => $n->image_url,
                'deep_link'  => $n->deep_link,
                'read'       => isset($readSet[$n->id]),
                'created_at' => ($n->sent_at ?? $n->created_at)?->toIso8601String(),
            ])->all();

        $unread = 0;
        foreach ($items as $i) {
            if (!$i['read']) {
                $unread++;
            }
        }

        return response()->json([
            'unread'        => $unread,
            'notifications' => $items,
        ]);
    }

    /**
     * POST /api/notifications/read   { id?: int }
     * Marks one notification read, or (no id) every one currently in the inbox.
     */
    public function markRead(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
        ]);

        $query = Notification::query()->sent()->forUser($user);
        if (($data['id'] ?? null) !== null) {
            $query->where('id', (int) $data['id']);
        }
        $ids = $query->pluck('id')->all();

        $now = now();
        foreach ($ids as $id) {
            NotificationRead::query()->firstOrCreate(
                ['notification_id' => $id, 'user_id' => $user->id],
                ['read_at' => $now],
            );
        }

        return response()->json(['ok' => true, 'marked' => count($ids)]);
    }

    /**
     * POST /api/devices/register   { token: string, platform?: string }
     * Stores/refreshes this device's FCM token (Phase 2 delivery). Idempotent.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (!$user instanceof User) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'token'    => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $user->id,
                'platform'     => $data['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    private function user(Request $request): ?User
    {
        $user = $request->attributes->get('auth_user');

        return $user instanceof User ? $user : null;
    }
}
