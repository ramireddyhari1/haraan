<?php

declare(strict_types=1);

namespace App\Filament\Clusters\GameHub\Pages;

use App\Filament\Clusters\GameHub\GameHubCluster;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use App\Models\VenueSlot;
use App\Services\BookingService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The web twin of the app's "Manage bookings" day screen: pick a venue + date,
 * see every slot's booked/capacity, add walk-in (offline) bookings, cancel, and
 * close/reopen the day. Scoped to the partner's own venues; admins see all.
 * Reuses the same BookingService as the partner API.
 */
class VenueBookings extends Page
{
    protected static ?string $cluster = GameHubCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Day bookings';

    protected static ?string $navigationLabel = 'Day bookings';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.game-hub.venue-bookings';

    public ?int $venueId = null;
    public string $date = '';
    public ?int $formSlotId = null;
    public ?string $guestName = null;
    public ?string $guestPhone = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('gamehub') ?? false;
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->venueId = $this->venueOptions()->keys()->first();
    }

    /** Venues this user may manage (own venues; super-admins see all). */
    protected function venuesQuery()
    {
        $query = Venue::query()->orderBy('name');
        $user = auth()->user();

        if ($user !== null && ! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->id);
        }

        return $query;
    }

    public function venueOptions(): Collection
    {
        return $this->venuesQuery()->pluck('name', 'id');
    }

    public function isBlocked(): bool
    {
        return $this->venueId !== null
            && VenueBlockedDate::query()->where('venue_id', $this->venueId)
                ->whereDate('date', $this->date)->exists();
    }

    /** @return array<int, array<string, mixed>> */
    public function slots(): array
    {
        if ($this->venueId === null) {
            return [];
        }

        $slots = VenueSlot::query()->where('venue_id', $this->venueId)->orderBy('sort_order')->get();

        $bookings = Booking::query()
            ->where('booking_type', 'venue')->where('venue_id', $this->venueId)
            ->whereDate('slot_date', $this->date)
            // Case-insensitive: match 'confirmed'/'CONFIRMED' alike so bookings
            // made via either path show on the day grid.
            ->whereRaw('lower(status) = ?', ['confirmed'])
            ->with('user:id,name')->get()->groupBy('venue_slot_id');

        return $slots->map(function (VenueSlot $s) use ($bookings): array {
            $b = $bookings->get($s->id) ?? collect();

            return [
                'id'        => $s->id,
                'label'     => trim(($s->day ?? '').' · '.($s->time ?? ''), " ·\t"),
                'time'      => $s->time,
                'price'     => (float) $s->price,
                'capacity'  => (int) $s->capacity,
                'booked'    => $b->count(),
                'available' => max((int) $s->capacity - $b->count(), 0),
                'bookings'  => $b->map(fn (Booking $x): array => [
                    'id'         => $x->id,
                    'customer'   => $x->guest_name ?: ($x->user?->name ?? 'Guest'),
                    'channel'    => $x->channel ?? 'online',
                    'checked_in' => (int) $x->checked_in_count,
                ])->values()->all(),
            ];
        })->all();
    }

    /** Open slots for the walk-in dropdown. @return array<int, string> */
    public function openSlotOptions(): array
    {
        $options = [];

        foreach ($this->slots() as $slot) {
            if ($slot['available'] > 0) {
                $options[$slot['id']] = $slot['label'].' — '.$slot['available'].' left';
            }
        }

        return $options;
    }

    public function addWalkIn(BookingService $bookings): void
    {
        $this->validate([
            'formSlotId' => ['required', 'integer'],
            'guestName'  => ['required', 'string', 'max:120'],
            'guestPhone' => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $bookings->createOfflineVenueBooking(
                auth()->user(),
                (int) $this->venueId,
                (int) $this->formSlotId,
                $this->date,
                $this->guestName,
                $this->guestPhone,
            );
            Notification::make()->title('Walk-in booked')->success()->send();
            $this->reset(['formSlotId', 'guestName', 'guestPhone']);
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function cancelBooking(int $id, BookingService $bookings): void
    {
        try {
            $bookings->cancelAsPartner(auth()->user(), (string) $id);
            Notification::make()->title('Booking cancelled')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function toggleClosed(): void
    {
        if ($this->venueId === null) {
            return;
        }

        if ($this->isBlocked()) {
            VenueBlockedDate::query()->where('venue_id', $this->venueId)
                ->whereDate('date', $this->date)->delete();
            Notification::make()->title('Day reopened')->success()->send();
        } else {
            VenueBlockedDate::query()->firstOrCreate([
                'venue_id' => $this->venueId,
                'date'     => $this->date,
            ]);
            Notification::make()->title('Day closed')->success()->send();
        }
    }
}
