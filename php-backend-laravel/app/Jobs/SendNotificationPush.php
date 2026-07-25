<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\Fcm\FcmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans a sent notification out to its audience's registered devices via FCM.
 *
 * Dispatched from Notification's saved() hook the moment it flips to 'sent'. Guarded
 * by pushed_at (set here on completion) so it runs exactly once per notification.
 * Dead tokens the server reports (unregistered/invalid) are pruned as we go.
 *
 * Queued so a large audience doesn't block the admin's save — with QUEUE_CONNECTION
 * =sync it still runs inline; add a worker and it goes async with retries.
 */
final class SendNotificationPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(FcmClient $fcm): void
    {
        $notification = Notification::find($this->notificationId);
        if (! $notification instanceof Notification || $notification->status !== 'sent') {
            return;
        }

        // Not configured (no service-account key): mark pushed so we don't retry a
        // no-op forever, and leave delivery to Reverb / the in-app bell.
        if (! $fcm->isConfigured()) {
            $notification->forceFill(['pushed_at' => now()])->saveQuietly();

            return;
        }

        $userIds = $notification->audienceUserIds();

        $data = [];
        if (! empty($notification->deep_link)) {
            $data['deep_link'] = $notification->deep_link;
        }

        if ($userIds->isNotEmpty()) {
            DeviceToken::query()
                ->whereIn('user_id', $userIds)
                ->chunkById(500, function ($tokens) use ($fcm, $notification, $data): void {
                    foreach ($tokens as $device) {
                        $result = $fcm->send(
                            $device->token,
                            (string) $notification->title,
                            (string) $notification->body,
                            $data,
                        );

                        if ($result === FcmClient::INVALID) {
                            $device->delete();
                        }
                    }
                });
        }

        // Stamp completion without re-firing model events (would re-dispatch this job).
        $notification->forceFill(['pushed_at' => now()])->saveQuietly();
    }
}
