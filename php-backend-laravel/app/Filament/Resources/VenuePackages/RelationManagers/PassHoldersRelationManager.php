<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenuePackages\RelationManagers;

use App\Models\AdminAction;
use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Everyone who bought this offer, with what they have left.
 *
 * Sessions left is derived (bought − redemption rows), so the only way to fix a
 * balance is to fix the rows: undo a session the desk spent by mistake. Every
 * correction needs a reason and lands in the audit log.
 */
class PassHoldersRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'Pass holders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('customer_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('redemptions'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('customer_name')->label('Customer')->weight('medium')->placeholder('Guest')
                    ->description(fn (CustomerPackage $record): string => '+91 ' . $record->customer_phone)
                    ->searchable(['customer_name', 'customer_phone']),
                TextColumn::make('created_at')->label('Bought')->date('d M Y')->sortable(),
                TextColumn::make('amount_paid')->label('Paid')->money('INR')
                    ->description(fn (CustomerPackage $record): string => match ($record->payment_method) {
                        'upi' => 'UPI', 'card' => 'Card', 'cash' => 'Cash', default => (string) $record->payment_method,
                    }),
                TextColumn::make('redemptions_count')
                    ->label('Sessions left')
                    ->state(fn (CustomerPackage $record): string => max($record->sessions_total - (int) $record->redemptions_count, 0) . ' of ' . $record->sessions_total)
                    ->color(fn (CustomerPackage $record): string => $record->sessions_total - (int) $record->redemptions_count <= 0 ? 'gray' : 'success'),
                TextColumn::make('expires_at')
                    ->label('Valid till')
                    ->state(fn (CustomerPackage $record): string => $record->expires_at === null
                        ? 'Never expires'
                        : ($record->isExpired() ? 'Expired ' : '') . $record->expires_at->timezone('Asia/Kolkata')->format('d M Y'))
                    ->color(fn (CustomerPackage $record): string => $record->isExpired() ? 'danger'
                        : ($record->expires_at !== null && $record->expires_at->lte(now()->addDays(7)) ? 'warning' : 'gray')),
            ])
            ->filters([
                Filter::make('usable')
                    ->label('Still usable')
                    ->query(fn (Builder $query): Builder => $query
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->whereRaw('sessions_total > (select count(*) from package_redemptions where package_redemptions.customer_package_id = customer_packages.id)')),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('sessions')
                    ->label('Sessions')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (CustomerPackage $record): string => ($record->customer_name ?: 'Guest') . ' · sessions used')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->schema(fn (CustomerPackage $record): array => [
                        TextEntry::make('uses')
                            ->hiddenLabel()
                            ->state(fn (): HtmlString => self::usesHtml($record)),
                    ]),
                Action::make('undoSession')
                    ->label('Undo a session')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (CustomerPackage $record): bool => (int) $record->redemptions_count > 0)
                    ->modalHeading('Give a session back')
                    ->modalDescription('Removes the most recent session spent from this pass, e.g. one the desk spent by mistake. The booking itself is not touched.')
                    ->schema([
                        Textarea::make('reason')->label('Reason (kept in the audit log)')->required()->maxLength(500),
                    ])
                    ->action(function (CustomerPackage $record, array $data): void {
                        $last = $record->redemptions()->latest('id')->first();
                        if ($last === null) {
                            return;
                        }
                        AdminAction::log('package_pass.session_undone', [
                            'customer_package_id' => $record->id,
                            'redemption' => $last->only(['id', 'booking_id', 'redeemed_at_venue_id', 'created_at']),
                            'reason' => $data['reason'],
                        ], $record);
                        $last->delete();
                        Notification::make()->title('Session given back')->success()->send();
                    }),
                Action::make('extend')
                    ->label('Change expiry')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->fillForm(fn (CustomerPackage $record): array => ['expires_at' => $record->expires_at?->timezone('Asia/Kolkata')->toDateString()])
                    ->schema([
                        DatePicker::make('expires_at')->label('Valid till')->native(false)
                            ->helperText('Clear it to make the pass never expire.'),
                        Textarea::make('reason')->label('Reason (kept in the audit log)')->required()->maxLength(500),
                    ])
                    ->action(function (CustomerPackage $record, array $data): void {
                        $before = $record->expires_at?->toIso8601String();
                        $new = empty($data['expires_at']) ? null : Carbon::parse($data['expires_at'], 'Asia/Kolkata')->endOfDay()->utc();
                        $record->forceFill(['expires_at' => $new])->save();
                        AdminAction::log('package_pass.expiry_changed', [
                            'customer_package_id' => $record->id,
                            'from' => $before,
                            'to' => $new?->toIso8601String(),
                            'reason' => $data['reason'],
                        ], $record);
                        Notification::make()->title('Expiry updated')->success()->send();
                    }),
            ]);
    }

    private static function usesHtml(CustomerPackage $pass): HtmlString
    {
        $venues = Venue::query()->where('partner_id', $pass->partner_id)->pluck('name', 'id');
        $rows = $pass->redemptions()->with('booking:id,slot_date,slot_label')->latest('id')->get();

        if ($rows->isEmpty()) {
            return new HtmlString('<p style="color:#64748b">No sessions used yet.</p>');
        }

        $n = $rows->count();
        $html = '<ol style="margin:0;padding:0;list-style:none">';
        foreach ($rows as $i => $r) {
            /** @var PackageRedemption $r */
            $when = $r->booking?->slot_date
                ? Carbon::parse($r->booking->slot_date)->format('d M Y') . ($r->booking->slot_label ? ' · ' . $r->booking->slot_label : '')
                : $r->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A');
            $where = $r->redeemed_at_venue_id !== null ? e($venues[$r->redeemed_at_venue_id] ?? 'Venue #' . $r->redeemed_at_venue_id) : '';
            $html .= '<li style="padding:6px 0;border-bottom:1px solid #f1f5f9"><strong>#' . ($n - $i) . '</strong> · '
                . e((string) $when) . ($where !== '' ? ' · ' . $where : '') . '</li>';
        }

        return new HtmlString($html . '</ol>');
    }
}
