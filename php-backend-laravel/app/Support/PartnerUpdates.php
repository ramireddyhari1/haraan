<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\PartnerUpdated;
use App\Models\DeviceToken;
use App\Models\MessageTemplate;
use App\Models\PartnerManager;
use App\Models\PartnerUpdate;
use App\Models\User;
use App\Services\Fcm\FcmClient;
use App\Services\TemplateResolver;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Tells a partner, everywhere at once, that Haraan changed something on their account:
 *
 *  1. a row in their updates feed (the app's bell, and the fallback the app polls);
 *  2. live, over the partner's private Reverb channel — an open app shows it instantly;
 *  3. a push to the owner's devices (the partner web app today);
 *  4. WhatsApp, through the `partner.account_update` template.
 *
 * Only changes made BY HARAAN count — an admin, finance, or the partner's Haraan
 * manager working in /control. A change the partner (or their desk staff) made
 * themselves is not news to them, and console/background work has no actor, so both
 * are skipped. Bursts collapse: the same kind of change on the same thing within a few
 * minutes updates one unread row instead of buzzing the phone again (a slot generator
 * saving 100 rows is ONE update), and WhatsApp goes out at most once per kind per half
 * hour. Nothing here can fail the admin's save: every channel is best-effort.
 */
final class PartnerUpdates
{
    public const TEMPLATE_KEY = 'partner.account_update';

    private const COALESCE_MINUTES = 5;

    private const WHATSAPP_GAP_MINUTES = 30;

    /** Turned off in tests that only care about the row. */
    public static bool $sideEffects = true;

    /**
     * @param  string  $screen  where the app opens it: payouts | venues | home | account
     */
    public static function record(
        int $partnerId,
        string $kind,
        string $title,
        ?string $body = null,
        string $screen = 'home',
        ?Model $subject = null,
        ?User $actor = null,
    ): ?PartnerUpdate {
        try {
            $actor ??= auth()->user();
            if (! $actor instanceof User || ! self::isHaraanSide($actor)) {
                return null;
            }
            $owner = User::query()->find($partnerId);
            if ($owner === null || $owner->parent_partner_id !== null || ! $owner->hasRoleEither(['PARTNER'])) {
                return null;
            }

            [$actorName, $actorKind] = self::actorLabel($actor, $partnerId, $kind);

            // A burst of the same change on the same thing is one update.
            $recent = PartnerUpdate::query()
                ->where('partner_id', $partnerId)
                ->where('kind', $kind)
                ->where('subject_type', $subject !== null ? class_basename($subject) : null)
                ->where('subject_id', $subject?->getKey())
                ->whereNull('seen_at')
                ->where('updated_at', '>=', now()->subMinutes(self::COALESCE_MINUTES))
                ->latest('id')
                ->first();

            if ($recent !== null) {
                $recent->fill(['title' => mb_substr($title, 0, 160), 'body' => $body, 'actor_name' => $actorName, 'actor_kind' => $actorKind]);
                $recent->isDirty() ? $recent->save() : $recent->touch();
                self::later(fn () => self::broadcast($recent));

                return $recent;
            }

            $update = PartnerUpdate::query()->create([
                'partner_id' => $partnerId,
                'kind' => $kind,
                'title' => mb_substr($title, 0, 160),
                'body' => $body,
                'screen' => $screen,
                'actor_name' => $actorName,
                'actor_kind' => $actorKind,
                'subject_type' => $subject !== null ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
            ]);

            self::later(function () use ($update, $owner): void {
                self::broadcast($update);
                self::push($update, $owner);
                self::whatsapp($update, $owner);
            });

            return $update;
        } catch (\Throwable $e) {
            Log::warning('Partner update could not be recorded for partner ' . $partnerId . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Haraan staff: exactly the people /control lets in (User::canAccessPanel) —
     * super-admins and the FINANCE / MARKETING / OPS desks. Never a partner, their desk
     * staff, or a member whose booking happened to touch a venue row.
     */
    public static function isHaraanSide(User $actor): bool
    {
        if ($actor->parent_partner_id !== null || $actor->hasRoleEither(['PARTNER'])) {
            return false;
        }

        return $actor->isSuperAdmin() || $actor->hasRoleEither(['FINANCE', 'MARKETING', 'OPS']);
    }

    /**
     * What the partner reads as "by …": their manager by name; finance work as
     * "Haraan finance"; anything else as "Haraan". Internal staff are never named
     * unless they're the manager the partner already knows.
     *
     * @return array{0: string, 1: string}
     */
    public static function actorLabel(User $actor, int $partnerId, string $kind = ''): array
    {
        if (PartnerManager::query()->where('partner_id', $partnerId)->where('manager_id', $actor->id)->exists()) {
            return [(string) $actor->name . ' (your Haraan manager)', 'manager'];
        }
        if (str_starts_with($kind, 'payout')) {
            return ['Haraan finance', 'finance'];
        }

        return ['Haraan', 'haraan'];
    }

    /** "₹1,250" — Indian grouping, no paise when whole. */
    public static function inr(float|int|string|null $v): string
    {
        $v = (float) $v;
        $whole = (int) round($v);
        $s = (string) abs($whole);
        if (strlen($s) > 3) {
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $s = $rest . ',' . $last3;
        }

        return ($whole < 0 ? '-₹' : '₹') . $s;
    }

    private static function later(callable $fn): void
    {
        if (! self::$sideEffects) {
            return;
        }
        // After the response on a web request, so a slow Reverb or WhatsApp call never
        // holds up the admin's save; inline when there's no request to wait for.
        if (app()->runningInConsole() || ! function_exists('Illuminate\Support\defer')) {
            $fn();

            return;
        }
        \Illuminate\Support\defer($fn);
    }

    private static function broadcast(PartnerUpdate $u): void
    {
        try {
            event(new PartnerUpdated($u));
        } catch (\Throwable $e) {
            Log::warning('Partner update broadcast failed: ' . $e->getMessage());
        }
    }

    private static function push(PartnerUpdate $u, User $owner): void
    {
        try {
            $fcm = app(FcmClient::class);
            if (! $fcm->isConfigured()) {
                return;
            }
            $data = ['type' => 'partner_update', 'kind' => $u->kind, 'screen' => $u->screen, 'update_id' => (string) $u->id];
            $body = trim(($u->body ? $u->body . ' ' : '') . '· ' . $u->actor_name, ' ·');

            DeviceToken::pushable()->where('user_id', $owner->id)->chunkById(100, function ($tokens) use ($fcm, $u, $body, $data): void {
                foreach ($tokens as $device) {
                    if ($fcm->send($device->token, $u->title, $body, $data) === FcmClient::INVALID) {
                        $device->delete();
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Partner update push failed: ' . $e->getMessage());
        }
    }

    private static function whatsapp(PartnerUpdate $u, User $owner): void
    {
        try {
            $phone = preg_replace('/[^0-9]/', '', (string) $owner->phone);
            if ($phone === null || strlen($phone) < 10) {
                return;
            }
            // /control → Platform → Templates: an inactive row is the admin's off switch.
            if (MessageTemplate::query()->where('key', self::TEMPLATE_KEY)->where('channel', 'whatsapp')->where('is_active', false)->exists()) {
                return;
            }
            // One WhatsApp per kind per half hour, however many edits.
            $sentRecently = PartnerUpdate::query()
                ->where('partner_id', $u->partner_id)
                ->where('kind', $u->kind)
                ->where('id', '<>', $u->id)
                ->where('whatsapp_sent_at', '>=', now()->subMinutes(self::WHATSAPP_GAP_MINUTES))
                ->exists();
            if ($sentRecently) {
                return;
            }

            $name = trim(explode(' ', trim((string) $owner->name))[0] ?? '') ?: 'there';
            $what = rtrim($u->title, '.') . '.' . ($u->body ? ' ' . $u->body : '');
            $ctx = MessageContext::platform(MessageContext::UTILITY, self::TEMPLATE_KEY);
            $route = app(TemplateResolver::class)->resolve(self::TEMPLATE_KEY, 'whatsapp', $phone);
            $wa = app(WhatsAppService::class);

            $sent = false;
            if ($route['mode'] === TemplateResolver::MODE_TEMPLATE) {
                $sent = (bool) $wa->sendTemplate($phone, (string) $route['name'], [$name, $what, (string) $u->actor_name], $ctx, (string) $route['language']);
            } elseif ($route['mode'] === TemplateResolver::MODE_FREE_TEXT) {
                $sent = (bool) $wa->sendMessage(
                    $phone,
                    "Hi {$name}, an update on your Haraan partner account:\n\n*{$u->title}*\n" . ($u->body ? $u->body . "\n" : '')
                        . "\nBy: {$u->actor_name}\n\nOpen the Haraan Partner app to see it.",
                    $ctx,
                );
            }
            if ($sent) {
                $u->forceFill(['whatsapp_sent_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('Partner update WhatsApp failed: ' . $e->getMessage());
        }
    }
}
