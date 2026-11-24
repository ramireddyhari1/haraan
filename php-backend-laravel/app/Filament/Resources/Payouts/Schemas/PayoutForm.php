<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts\Schemas;

use App\Filament\Resources\PayoutBatches\Schemas\PayoutBatchForm;
use App\Models\Booking;
use App\Models\Payout;
use App\Services\PartnerSettlement;
use App\Support\BusinessClock;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/**
 * One booking's settlement to its partner.
 *
 * The scaffolded form asked for a raw booking id, a free-text status and an
 * amount defaulting to ₹0, and had nowhere to record how the money went out.
 * Now the booking is found by what admins actually know (customer, event or
 * venue, order id), picking it fills the partner's share and shows where the
 * money should land, and a booking that already has a live payout can't be
 * paid a second time.
 */
class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    self::bookingSection(),
                    self::transferSection(),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    self::amountSection(),
                ])->columnSpan(['lg' => 1]),
            ]);
    }

    private static function bookingSection(): Section
    {
        return Section::make('Booking')
            ->description('Only paid bookings without a live payout are listed.')
            ->schema([
                Select::make('booking_id')
                    ->label('Booking')
                    ->placeholder('Search customer, event, venue, order id or #id')
                    ->searchable()
                    ->required()
                    ->live()
                    ->disabled(fn (?Payout $record): bool => self::isLocked($record))
                    ->dehydrated()
                    ->getSearchResultsUsing(fn (string $search, ?Payout $record): array => self::searchBookings($search, $record))
                    ->getOptionLabelUsing(fn ($value): ?string => ($b = self::booking($value)) ? self::bookingLabel($b) : null)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (($b = self::booking($state)) !== null) {
                            $set('amount', self::partnerShare($b));
                        }
                    })
                    // Never two live payouts for one booking: that is paying the partner twice.
                    // A failed payout doesn't count, so it can be retried.
                    ->unique(
                        table: 'payouts',
                        column: 'booking_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where(
                            fn ($q) => $q->whereRaw('lower(status) != ?', ['failed'])
                        ),
                    )
                    ->validationMessages(['unique' => 'This booking already has a payout. Edit that one instead.']),
                Text::make(fn (Get $get): HtmlString => self::bookingSummary($get('booking_id'))),
            ]);
    }

    private static function amountSection(): Section
    {
        return Section::make('Payout')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount to partner')
                    ->prefix('₹')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(fn (Get $get): ?float => self::booking($get('booking_id'))?->total_amount ?: null)
                    ->validationMessages(['max' => 'More than the customer paid for this booking.'])
                    ->required()
                    ->disabled(fn (?Payout $record): bool => self::isLocked($record))
                    ->dehydrated()
                    ->helperText('Filled with the partner\'s share: what the customer paid, less Haraan\'s fees and commission.'),
                ToggleButtons::make('status')
                    ->options(Payout::STATUSES)
                    // Pending is the resting state, not an alarm: neutral, not a filled orange.
                    ->colors(['pending' => 'gray', 'processed' => 'success', 'failed' => 'danger'])
                    ->default('pending')
                    ->inline()
                    ->required()
                    ->live()
                    // Older rows say PENDING; match them to an option.
                    ->formatStateUsing(fn (?string $state): string => strtolower((string) ($state ?: 'pending')))
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if ($state === 'processed' && blank($get('processed_at'))) {
                            $set('processed_at', now()->toDateTimeString());
                        }
                    })
                    ->helperText('Mark Processed only once the money has actually left.'),
                DateTimePicker::make('processed_at')
                    ->label('Paid on')
                    ->timezone(BusinessClock::zone())
                    ->native(false)
                    ->displayFormat('d M Y, h:i A')
                    ->seconds(false)
                    ->maxDate(now()->addDay())
                    ->visible(fn (Get $get): bool => $get('status') === 'processed')
                    ->required(fn (Get $get): bool => $get('status') === 'processed'),
            ]);
    }

    private static function transferSection(): Section
    {
        return Section::make('Transfer')
            ->description('How the money went out, so the partner and finance can trace it.')
            ->columns(2)
            ->schema([
                ToggleButtons::make('method')
                    ->label('Paid via')
                    ->options(Payout::METHODS)
                    ->inline()
                    ->required(fn (Get $get): bool => $get('status') === 'processed'),
                TextInput::make('reference')
                    ->label('Reference / UTR')
                    ->maxLength(120)
                    ->placeholder(fn (Get $get): string => $get('method') === 'cash' ? 'Receipt no.' : 'e.g. 4321 0987 6543')
                    ->required(fn (Get $get): bool => $get('status') === 'processed' && in_array($get('method'), ['upi', 'bank'], true))
                    ->validationMessages(['required' => 'Add the UTR so the partner can trace this transfer.']),
                Textarea::make('note')
                    ->label('Internal note')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    /** A processed payout is money that has left; its booking and amount stay as recorded. */
    private static function isLocked(?Payout $record): bool
    {
        return $record !== null && in_array(strtolower((string) $record->getOriginal('status')), PartnerSettlement::SETTLED_PAYOUT, true);
    }

    public static function booking(mixed $id): ?Booking
    {
        if (blank($id)) {
            return null;
        }

        return Booking::query()->with(['user:id,name,email,phone', 'event:id,title,partner_id', 'venue:id,name,partner_id'])->find($id);
    }

    /**
     * What Haraan owes the partner for this booking: the ticket/slot value less the platform's
     * deduction, less any advance-booking balance the venue collected itself at the desk.
     */
    public static function partnerShare(Booking $b): float
    {
        return $b->partnerPayable();
    }

    public static function partnerId(Booking $b): ?int
    {
        $id = $b->event?->partner_id ?? $b->venue?->partner_id;

        return $id ? (int) $id : null;
    }

    public static function customerName(Booking $b): string
    {
        return (string) ($b->attendee_name ?: $b->guest_name ?: $b->user?->name ?: 'Guest');
    }

    public static function forLabel(Booking $b): string
    {
        return (string) ($b->event?->title ?? $b->venue?->name ?? 'Booking');
    }

    /** "#482 · Sunset Turf · Ravi Kumar · ₹1,200 · 12 Nov" */
    public static function bookingLabel(Booking $b): string
    {
        return '#' . $b->id
            . ' · ' . self::forLabel($b)
            . ' · ' . self::customerName($b)
            . ' · ' . PartnerSettlement::inr((float) $b->total_amount)
            . ' · ' . $b->created_at?->timezone(BusinessClock::zone())->format('d M');
    }

    /** @return array<int, string> */
    private static function searchBookings(string $search, ?Payout $record): array
    {
        $search = trim($search);
        $like = '%' . $search . '%';

        return Booking::query()
            ->with(['user:id,name', 'event:id,title,partner_id', 'venue:id,name,partner_id'])
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::PAID_BOOKING)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('payout', fn (Builder $p) => $p->whereRaw('lower(status) != ?', ['failed']))
                ->when($record, fn (Builder $q) => $q->orWhere('id', $record->booking_id)))
            ->where(fn (Builder $q) => $q
                ->when(ctype_digit(ltrim($search, '#')), fn (Builder $q) => $q->orWhere('id', (int) ltrim($search, '#')))
                ->orWhere('razorpay_order_id', 'like', $like)
                ->orWhere('attendee_name', 'like', $like)
                ->orWhere('guest_name', 'like', $like)
                ->orWhere('guest_phone', 'like', $like)
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->orWhereHas('event', fn (Builder $e) => $e->where('title', 'like', $like))
                ->orWhereHas('venue', fn (Builder $v) => $v->where('name', 'like', $like)))
            ->latest('id')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Booking $b): array => [$b->id => self::bookingLabel($b)])
            ->all();
    }

    /** The facts the decision needs, once a booking is picked: the split, the partner, where it lands. */
    private static function bookingSummary(mixed $bookingId): HtmlString
    {
        $b = self::booking($bookingId);

        $muted = 'color:var(--hrn-ink-3,var(--gray-500))';

        if ($b === null) {
            return new HtmlString('<span style="font-size:.8125rem;' . $muted . '">Pick a booking to see the partner\'s share and where to send it.</span>');
        }

        $partnerId = self::partnerId($b);
        $partner = $partnerId ? \App\Models\User::query()->whereKey($partnerId)->value('name') : null;
        $e = fn (?string $s): string => e((string) $s);

        $paid = (float) $b->total_amount;
        $share = self::partnerShare($b);

        // The same parts Booking::partnerPayable() takes off, each named, so the slip adds up.
        $offs = [];
        if ($b->isDeskBooking()) {
            // Desk bookings: only the online part passed through Haraan; the rest the desk took.
            $offs['Paid at the desk'] = max(0.0, $paid - (float) $b->host_deduction - $share);
        } else {
            $offs['Booking fee (Haraan)'] = $paid - $b->slotValue();
            $offs['Collected at the venue'] = (float) ($b->pay_at_venue ?? 0);
        }
        $offs['Haraan commission'] = (float) $b->host_deduction;
        // partnerPayable() floors at zero; anything left over shows as its own line.
        $rest = round($paid - array_sum($offs) - $share, 2);
        if (abs($rest) > 0.009) {
            $offs['Adjustment'] = $rest;
        }

        $offs = array_filter($offs, fn ($v) => abs((float) $v) > 0.009);

        $num = 'text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap';
        $line = fn (string $k, string $v, string $extra = ''): string => '<tr><td style="padding:3px 0;' . $muted . $extra . '">' . $k
            . '</td><td style="padding:3px 0;' . $num . $extra . '">' . $v . '</td></tr>';

        // The split, set like a receipt: what came in, what comes off, what the partner gets.
        $slip = '<table style="width:100%;max-width:22rem;border-collapse:collapse;font-size:.8125rem">'
            . $line('Customer paid', PartnerSettlement::inr($paid))
            . implode('', array_map(
                fn (string $k, float $v): string => $line($k, ($v < 0 ? '+ ' : '− ') . PartnerSettlement::inr(abs($v))),
                array_keys($offs),
                $offs,
            ))
            . '<tr><td style="padding:8px 0 0;border-top:1px solid var(--hrn-border,var(--gray-200));font-weight:650;color:var(--hrn-ink,inherit)">Partner share</td>'
            . '<td style="padding:8px 0 0;border-top:1px solid var(--hrn-border,var(--gray-200));font-weight:700;font-size:.9375rem;color:var(--hrn-ink,inherit);' . $num . '">'
            . PartnerSettlement::inr($share) . '</td></tr>'
            . '</table>';

        $facts = [
            ['Customer', $e(self::customerName($b)) . ' · ' . $e(ucfirst(strtolower((string) $b->status)))],
            ['Partner', $partner ? $e($partner) : '<span style="color:var(--danger-600)">No partner on this event or venue</span>'],
            ['Send to', str_starts_with($dest = PayoutBatchForm::destinationLine($partnerId), 'No settlement account')
                ? '<span style="color:var(--danger-600)">' . $e($dest) . '</span>'
                : $e($dest)],
        ];
        $dl = '<dl style="display:grid;grid-template-columns:max-content 1fr;gap:4px 20px;margin:0 0 14px;font-size:.8125rem">';
        foreach ($facts as [$k, $v]) {
            $dl .= '<dt style="' . $muted . '">' . $k . '</dt><dd style="margin:0;color:var(--hrn-ink,inherit)">' . $v . '</dd>';
        }

        return new HtmlString($dl . '</dl>' . $slip);
    }
}
