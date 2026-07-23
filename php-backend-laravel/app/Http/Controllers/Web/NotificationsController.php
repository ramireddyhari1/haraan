<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\View\View;

/**
 * The website's bell inbox — the same notifications the app lists, reached from
 * the header's bell icon. Reuses the Notification scopes the API twin
 * ({@see \App\Http\Controllers\Api\NotificationsController}) uses, so audience
 * targeting and read state stay identical across clients.
 */
final class NotificationsController extends Controller
{
    /**
     * GET /notifications — the inbox, newest first. Opening it marks everything
     * shown as read, which is what the bell's badge counts.
     */
    public function index(): View
    {
        $user = auth()->user();

        $notifications = Notification::query()
            ->sent()
            ->forUser($user)
            ->limit(50)
            ->get();

        $readIds = NotificationRead::query()
            ->where('user_id', $user->id)
            ->pluck('notification_id')
            ->all();
        $readSet = array_flip($readIds);

        // Which were unread *as the page was opened* — the list still marks them
        // so the reader can see what's new on this visit.
        $wasUnread = $notifications
            ->reject(fn (Notification $n): bool => isset($readSet[$n->id]))
            ->pluck('id')
            ->all();

        $now = now();
        foreach ($wasUnread as $id) {
            NotificationRead::query()->firstOrCreate(
                ['notification_id' => $id, 'user_id' => $user->id],
                ['read_at' => $now],
            );
        }

        return view('site.notifications', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
            'wasUnread'     => array_flip($wasUnread),
        ]);
    }
}
