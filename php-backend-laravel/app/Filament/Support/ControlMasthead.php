<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Booking;
use App\Models\Hrms\EmployeeAttendance;
use App\Models\Event;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\Venue;
use App\Support\BusinessClock;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The drawn masthead every /control list and workspace page opens with: which part of the
 * business you are in (an eyebrow and a line drawing of that area) and one line of facts
 * only Haraan knows right now ("13 on sale · 6 this week").
 *
 * Wired by three render hooks in AdminPanelProvider (art at PAGE_START, eyebrow before the
 * heading, facts after it). Forms (create/edit/view) and pages that draw their own hero —
 * Command Center, the dashboard — are left alone.
 */
final class ControlMasthead
{
    /** Area => [eyebrow, scene name]. Scene names are cases in components/hrn-scene. */
    public const AREAS = [
        'events' => ['Events & tickets', 'events'],
        'gamehub' => ['GameHub · venues & play', 'gamehub'],
        'finance' => ['Finance', 'finance'],
        'marketing' => ['Marketing', 'marketing'],
        'rewards' => ['Rewards', 'rewards'],
        'content' => ['App content', 'content'],
        'support' => ['Support & moderation', 'support'],
        'people' => ['People', 'people'],
        'workforce' => ['Workforce & HR', 'workforce'],
        'platform' => ['Platform', 'platform'],
        'system' => ['System', 'system'],
    ];

    /** Navigation group / cluster name => area. */
    private const GROUPS = [
        'events' => 'events',
        'gamehub' => 'gamehub',
        'finance' => 'finance',
        'marketing' => 'marketing',
        'rewards' => 'rewards',
        'app content' => 'content',
        'support & moderation' => 'support',
        'people' => 'people',
        'workforce & hr' => 'workforce',
        'team & hrms' => 'workforce',
        'platform' => 'platform',
        'system' => 'system',
    ];

    /** Pages that already open with their own hero. */
    private const OWN_HERO = [
        \App\Filament\Pages\CommandCenter::class,
        \App\Filament\Pages\Dashboard::class,
    ];

    /** Empty-table drawing per area (names from components/hrn-illo). */
    public const EMPTY_ILLO = [
        'events' => 'ticket',
        'gamehub' => 'court-empty',
        'finance' => 'receipt',
        'marketing' => 'paper-plane',
        'rewards' => 'trophy',
        'content' => 'photo',
        'support' => 'chat',
        'people' => 'players',
        'workforce' => 'whistle',
        'platform' => 'switchboard',
        'system' => 'clipboard',
    ];

    /**
     * The area a page belongs to, or null when it should keep Filament's plain header.
     *
     * @param  array<int, string>  $scopes  render-hook scopes: the page class, then its resource
     */
    public static function areaFor(array $scopes): ?string
    {
        $page = collect($scopes)->first(fn ($s) => is_string($s) && is_subclass_of($s, Page::class));
        if (! $page || in_array($page, self::OWN_HERO, true) || is_subclass_of($page, BaseDashboard::class)) {
            return null;
        }
        foreach ([CreateRecord::class, EditRecord::class, ViewRecord::class] as $form) {
            if (is_subclass_of($page, $form)) {
                return null;
            }
        }

        $resource = collect($scopes)->first(fn ($s) => is_string($s) && is_subclass_of($s, \Filament\Resources\Resource::class));

        foreach (array_filter([$page, $resource]) as $owner) {
            try {
                $cluster = method_exists($owner, 'getCluster') ? $owner::getCluster() : null;
                if ($cluster && ($area = self::GROUPS[strtolower(str_replace('Cluster', '', class_basename($cluster)))] ?? null)) {
                    return $area;
                }
            } catch (Throwable) {
            }
        }

        foreach (array_filter([$resource, $page]) as $owner) {
            try {
                $group = method_exists($owner, 'getNavigationGroup') ? $owner::getNavigationGroup() : null;
            } catch (Throwable) {
                $group = null;
            }
            $group = $group instanceof \UnitEnum ? $group->name : $group;
            if (is_string($group) && ($area = self::GROUPS[strtolower($group)] ?? null)) {
                return $area;
            }
        }

        return null;
    }

    /** The area a sidebar navigation group belongs to ("Workforce & HR" => workforce). */
    public static function areaForGroup(?string $label): ?string
    {
        return $label === null ? null : (self::GROUPS[strtolower($label)] ?? null);
    }

    public static function eyebrow(string $area): string
    {
        return self::AREAS[$area][0] ?? '';
    }

    public static function scene(string $area): string
    {
        return self::AREAS[$area][1] ?? $area;
    }

    /**
     * One to three live facts for the area, each [value, label]. Cached for a minute so a
     * page of Livewire round-trips doesn't re-count; any failure just drops the line.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function facts(string $area): array
    {
        return Cache::remember("control-masthead:{$area}", 60, function () use ($area): array {
            try {
                return array_values(array_filter(self::computeFacts($area)));
            } catch (Throwable $e) {
                report($e);

                return [];
            }
        });
    }

    private static function computeFacts(string $area): array
    {
        $today = BusinessClock::today();
        $n = fn (int|float $v): string => number_format((float) $v);

        return match ($area) {
            'events' => [
                [$n(Event::query()->onSale()->count()), 'on sale'],
                [$n(Event::query()->onSale()->whereDate('date', '<=', now()->addDays(7)->toDateString())->count()), 'in the next 7 days'],
                ($pending = Event::query()->whereRaw('lower(status) = ?', ['pending'])->count()) > 0
                    ? [$n($pending), 'waiting for review'] : null,
            ],
            'gamehub' => [
                [$n(Venue::query()->published()->count()), 'venues taking bookings'],
                [$n(Booking::query()->whereNotNull('venue_id')->whereDate('slot_date', $today)
                    ->whereNotIn(DB::raw('lower(status)'), ['cancelled', 'canceled', 'refunded', 'failed', 'expired'])->count()), 'court bookings today'],
            ],
            'finance' => [
                ['₹' . $n(Booking::query()->whereIn(DB::raw('lower(status)'), ['confirmed', 'paid', 'completed', 'checked_in'])
                    ->whereDate('created_at', $today)->sum('total_amount')), 'taken today'],
                ($payouts = \App\Models\Payout::query()->whereRaw('lower(status) = ?', ['pending'])->count()) > 0
                    ? [$n($payouts), $payouts === 1 ? 'payout to send' : 'payouts to send'] : null,
            ],
            'people' => [
                [$n(User::query()->count()), 'accounts'],
                [$n(User::query()->where('created_at', '>=', now()->subDays(7))->count()), 'joined this week'],
            ],
            'support' => [
                [$n(SupportThread::query()->where('status', '!=', 'closed')->where('admin_unread_count', '>', 0)->count()), 'waiting on a reply'],
            ],
            'workforce' => [
                [$n(EmployeeAttendance::query()->whereDate('date', BusinessClock::todayDate())->whereNotNull('clock_in_at')->count()), 'clocked in today'],
            ],
            default => [],
        };
    }
}
