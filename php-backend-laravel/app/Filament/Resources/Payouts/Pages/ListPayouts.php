<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Booking;
use App\Models\Payout;
use App\Services\PartnerSettlement;
use App\Support\BusinessClock;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Record payout'),
        ];
    }

    /** One line of what is actually true: who is still waiting, and what left this month. */
    public function getSubheading(): ?string
    {
        $waiting = self::waitingCount();

        $local = now(BusinessClock::zone());
        $paid = (float) Payout::query()
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::SETTLED_PAYOUT)
            ->where('processed_at', '>=', $local->copy()->startOfMonth()->utc())
            ->sum('amount');

        $month = $local->format('F');

        return match ($waiting) {
            0 => 'Every paid booking has a payout',
            1 => '1 paid booking has no payout yet',
            default => $waiting . ' paid bookings have no payout yet',
        } . ' · ' . PartnerSettlement::inr($paid) . ' paid out in ' . $month;
    }

    /** Paid bookings with no live (non-failed) payout — the same pool the form's search draws from. */
    public static function waitingCount(): int
    {
        return Booking::query()
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::PAID_BOOKING)
            ->whereDoesntHave('payout', fn (Builder $p) => $p->whereRaw('lower(status) != ?', ['failed']))
            ->count();
    }
}
