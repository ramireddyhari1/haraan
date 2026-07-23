<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

/**
 * Who actually bought — gender, age band, location and device, computed from the real accounts
 * behind this event's paid bookings (users.gender / date_of_birth / district / state / user_agent).
 * No demographic is invented: an "Unknown" bucket carries buyers whose profile field is blank, so
 * the bars always reconcile to the true buyer count. Read-only; record injected by the page.
 */
class EventAudienceWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-audience';

    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    /**
     * @return array{buyers:int,gender:array,age:array,location:array,device:array}
     */
    public function getBreakdown(): array
    {
        $event = $this->record;
        if (! $event) {
            return ['buyers' => 0, 'gender' => [], 'age' => [], 'location' => [], 'device' => []];
        }

        $userIds = Booking::where('event_id', $event->id)
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereNotNull('user_id')->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            return ['buyers' => 0, 'gender' => [], 'age' => [], 'location' => [], 'device' => []];
        }

        $users = User::whereIn('id', $userIds)->get(['gender', 'date_of_birth', 'age', 'district', 'state', 'user_agent']);
        $total = $users->count();

        $raw = [
            'gender' => $this->rank($users->map(fn ($u) => $this->genderLabel($u->gender)), $total),
            'age' => $this->rank($users->map(fn ($u) => $this->ageBand($u->date_of_birth, $u->age)), $total, keepOrder: [
                'Under 18', '18–24', '25–34', '35–44', '45+', 'Unknown',
            ]),
            'location' => $this->rank($users->map(fn ($u) => $this->place($u)), $total, limit: 5),
            'device' => $this->rank($users->map(fn ($u) => $this->deviceLabel($u->user_agent)), $total),
        ];

        return [
            'buyers' => $total,
            'dimensions' => $this->buildDimensions($raw),
        ];
    }

    /**
     * Wrap each dimension for the view: an icon, per-segment colours (Unknown is
     * ALWAYS a muted gray so it never masquerades as a real bucket), whether any
     * real data exists, and an honest caption for the no-data state.
     *
     * @param  array<string,array<int,array{label:string,count:int,pct:int}>>  $raw
     * @return array<int,array<string,mixed>>
     */
    private function buildDimensions(array $raw): array
    {
        $meta = [
            'gender'   => ['Gender', 'heroicon-m-users', "These buyers haven't shared their gender yet."],
            'age'      => ['Age', 'heroicon-m-cake', "These buyers haven't shared their age yet."],
            'location' => ['Top locations', 'heroicon-m-map-pin', 'No location saved on these profiles yet.'],
            'device'   => ['Device', 'heroicon-m-device-phone-mobile', "Device wasn't captured for these buyers."],
        ];

        $dims = [];
        foreach ($meta as $key => [$title, $icon, $empty]) {
            $rows = [];
            $hasReal = false;
            foreach (($raw[$key] ?? []) as $i => $r) {
                $gray = $r['label'] === 'Unknown';
                $hasReal = $hasReal || ! $gray;
                $rows[] = [
                    'label' => $r['label'],
                    'count' => $r['count'],
                    'pct'   => $r['pct'],
                    'color' => $this->colorFor($key, $r['label'], $i),
                    'gray'  => $gray,
                ];
            }

            $dims[] = [
                'key'     => $key,
                'title'   => $title,
                'icon'    => $icon,
                'hasReal' => $hasReal,
                'rows'    => $rows,
                'empty'   => $empty,
            ];
        }

        return $dims;
    }

    /** Segment colour. Unknown → gray; gender/device are label-mapped; ordinal dims ramp by index. */
    private function colorFor(string $key, string $label, int $idx): string
    {
        if ($label === 'Unknown') {
            return '#94a3b8';
        }

        return match ($key) {
            'gender' => match ($label) {
                'Male' => '#2563eb',
                'Female' => '#ec4899',
                default => '#8b5cf6',
            },
            'device' => match ($label) {
                'Android' => '#16a34a',
                'iPhone' => '#0ea5e9',
                'iPad' => '#6366f1',
                'Web' => '#f59e0b',
                default => '#8b5cf6',
            },
            'age' => ['#12b76a', '#10b981', '#059669', '#047857', '#065f46'][$idx % 5],
            'location' => ['#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'][$idx % 5],
            default => '#2563eb',
        };
    }

    /**
     * Count + percentage per label, biggest first (or a fixed order for ordinal bands).
     *
     * @param  Collection<int,string>  $labels
     * @param  array<int,string>|null  $keepOrder  fixed display order (used for age bands)
     * @return array<int,array{label:string,count:int,pct:int}>
     */
    private function rank(Collection $labels, int $total, ?array $keepOrder = null, ?int $limit = null): array
    {
        $counts = $labels->countBy();

        if ($keepOrder !== null) {
            $rows = collect($keepOrder)
                ->map(fn ($k) => ['label' => $k, 'count' => (int) ($counts[$k] ?? 0)])
                ->filter(fn ($r) => $r['count'] > 0);
        } else {
            $rows = $counts->map(fn ($c, $k) => ['label' => (string) $k, 'count' => (int) $c])
                ->sortByDesc('count')->values();
        }

        $rows = $rows->map(fn ($r) => [
            'label' => $r['label'],
            'count' => $r['count'],
            'pct' => $total > 0 ? (int) round($r['count'] / $total * 100) : 0,
        ])->values();

        return ($limit ? $rows->take($limit) : $rows)->all();
    }

    private function genderLabel(?string $g): string
    {
        $g = strtolower(trim((string) $g));

        return match (true) {
            in_array($g, ['m', 'male', 'man'], true) => 'Male',
            in_array($g, ['f', 'female', 'woman'], true) => 'Female',
            $g === '' => 'Unknown',
            default => 'Other',
        };
    }

    private function ageBand($dob, $ageColumn = null): string
    {
        if ($dob) {
            // date_of_birth is cast to Carbon on the model, but tolerate a raw string too.
            $age = Carbon::parse($dob)->age;
        } elseif ($ageColumn !== null && (int) $ageColumn > 0) {
            // No DOB — fall back to the integer `age` collected at sign-up.
            $age = (int) $ageColumn;
        } else {
            return 'Unknown';
        }

        return match (true) {
            $age < 18 => 'Under 18',
            $age <= 24 => '18–24',
            $age <= 34 => '25–34',
            $age <= 44 => '35–44',
            default => '45+',
        };
    }

    private function place(User $u): string
    {
        $d = trim((string) $u->district);
        if ($d !== '') {
            return $d;
        }
        $s = trim((string) $u->state);

        return $s !== '' ? $s : 'Unknown';
    }

    private function deviceLabel(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return 'Unknown';
        }

        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            (bool) preg_match('/Windows|Macintosh|Linux|X11|CrOS/', $ua) => 'Web',
            (bool) preg_match('/okhttp|Dalvik/', $ua) => 'Android',
            default => 'Other',
        };
    }
}
