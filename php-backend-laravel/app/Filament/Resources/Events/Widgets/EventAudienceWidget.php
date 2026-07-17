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

        $users = User::whereIn('id', $userIds)->get(['gender', 'date_of_birth', 'district', 'state', 'user_agent']);
        $total = $users->count();

        return [
            'buyers' => $total,
            'gender' => $this->rank($users->map(fn ($u) => $this->genderLabel($u->gender)), $total),
            'age' => $this->rank($users->map(fn ($u) => $this->ageBand($u->date_of_birth)), $total, keepOrder: [
                'Under 18', '18–24', '25–34', '35–44', '45+', 'Unknown',
            ]),
            'location' => $this->rank($users->map(fn ($u) => $this->place($u)), $total, limit: 5),
            'device' => $this->rank($users->map(fn ($u) => $this->deviceLabel($u->user_agent)), $total),
        ];
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

    private function ageBand($dob): string
    {
        if (! $dob) {
            return 'Unknown';
        }
        // date_of_birth is cast to Carbon on the model, but tolerate a raw string too.
        $age = Carbon::parse($dob)->age;

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
