<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerPayoutAccounts;

use App\Filament\Resources\PartnerPayoutAccounts\Pages\ListPartnerPayoutAccounts;
use App\Models\PartnerManager;
use App\Models\PartnerPayoutAccount;
use App\Models\User;
use App\Support\PayoutAccountEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Settlement destinations: where each partner's money is sent.
 *
 * Who can do what (2026-10-09):
 *  - Finance / admin: see every partner's full details, add or change them, verify.
 *  - A partner's Haraan manager: sees and changes only the partners assigned to them
 *    (PartnerResource → Haraan manager). They can't verify — someone else vouches.
 *  - The partner: changes it in the app or /partner → Payouts.
 * Every change goes through PayoutAccountEditor: it clears verification, records who
 * did it (the partner app shows that line), and pushes the owner when it wasn't them.
 */
class PartnerPayoutAccountResource extends Resource
{
    protected static ?string $model = PartnerPayoutAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $cluster = \App\Filament\Clusters\Finance\FinanceCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'settlement account';

    protected static ?string $navigationLabel = 'Settlement accounts';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->canManage('finance') || PartnerManager::query()->where('manager_id', $u->id)->exists());
    }

    public static function canCreate(): bool
    {
        return false; // "Add for a partner" on the list page goes through the editor instead.
    }

    /** Managers only ever see their own partners. */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['partner']);
        $u = auth()->user();
        if ($u !== null && ! $u->canManage('finance')) {
            $q->whereIn('partner_id', PayoutAccountEditor::managedPartnerIds($u));
        }

        return $q;
    }

    /** The bank/UPI fields, shared by Add and Edit. @return array<int, mixed> */
    public static function destinationFields(): array
    {
        return [
            ToggleButtons::make('method')
                ->label('Paid to')
                ->options(['bank' => 'Bank account', 'upi' => 'UPI'])
                ->icons(['bank' => 'heroicon-m-building-library', 'upi' => 'heroicon-m-qr-code'])
                ->inline()->required()->live()->default('upi'),
            TextInput::make('account_holder')->label('Account holder name')->required()->minLength(2)->maxLength(120),
            TextInput::make('bank_name')->label('Bank name')->maxLength(120)
                ->visible(fn (Get $get): bool => $get('method') === 'bank')->required(fn (Get $get): bool => $get('method') === 'bank'),
            TextInput::make('account_number')->label('Account number')
                ->regex('/^\d{6,18}$/')->validationMessages(['regex' => 'Digits only, 6–18 of them.'])
                ->visible(fn (Get $get): bool => $get('method') === 'bank')->required(fn (Get $get): bool => $get('method') === 'bank'),
            TextInput::make('ifsc_code')->label('IFSC')
                ->regex('/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/')->validationMessages(['regex' => 'e.g. HDFC0001234'])
                ->visible(fn (Get $get): bool => $get('method') === 'bank')->required(fn (Get $get): bool => $get('method') === 'bank'),
            TextInput::make('upi_vpa')->label('UPI ID')->placeholder('name@okhdfc')
                ->regex('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/')->validationMessages(['regex' => 'e.g. name@okhdfc'])
                ->visible(fn (Get $get): bool => $get('method') === 'upi')->required(fn (Get $get): bool => $get('method') === 'upi'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('partner.name')
                    ->label('Partner')
                    ->weight('bold')
                    ->description(fn (PartnerPayoutAccount $r): ?string => PartnerManager::query()->where('partner_id', $r->partner_id)->first()?->manager?->name
                        ? 'Manager: ' . PartnerManager::query()->where('partner_id', $r->partner_id)->first()->manager->name
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => $state === 'upi' ? 'info' : 'gray'),
                TextColumn::make('account_holder')
                    ->label('Destination')
                    // Staff see the full destination: they're the ones checking it.
                    ->description(fn (PartnerPayoutAccount $r): string => $r->method === 'upi'
                        ? (string) $r->upi_vpa
                        : trim(($r->bank_name ? $r->bank_name . ' · ' : '') . $r->account_number . ($r->ifsc_code ? ' · ' . $r->ifsc_code : '')))
                    ->placeholder('—')
                    ->searchable(['account_holder', 'upi_vpa', 'account_number']),
                IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-badge')
                    ->falseIcon('heroicon-m-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->dateTime('d M Y H:i')
                    ->description(fn (PartnerPayoutAccount $r): ?string => match ($r->updated_by_kind) {
                        PayoutAccountEditor::KIND_PARTNER => 'by the partner',
                        PayoutAccountEditor::KIND_MANAGER => 'by manager ' . (User::find($r->updated_by_id)?->name ?? ''),
                        PayoutAccountEditor::KIND_HARAAN => 'by ' . (User::find($r->updated_by_id)?->name ?? 'Haraan'),
                        default => null,
                    })
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('verified_at')
                    ->label('Verification')
                    ->placeholder('All')
                    ->trueLabel('Verified')
                    ->falseLabel('Awaiting verification')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('verified_at'),
                        false: fn ($q) => $q->whereNull('verified_at'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->modalHeading(fn (PartnerPayoutAccount $r): string => 'Settlement account · ' . ($r->partner?->name ?? 'partner'))
                    ->modalDescription('Saving clears verification and tells the partner on their phone who changed it.')
                    ->visible(fn (PartnerPayoutAccount $r): bool => PayoutAccountEditor::staffMayEdit(auth()->user(), (int) $r->partner_id))
                    ->fillForm(fn (PartnerPayoutAccount $r): array => $r->only(['method', 'account_holder', 'bank_name', 'account_number', 'ifsc_code', 'upi_vpa']))
                    ->schema(self::destinationFields())
                    ->action(function (PartnerPayoutAccount $r, array $data): void {
                        app(PayoutAccountEditor::class)->save((int) $r->partner_id, $data, auth()->user());
                        Notification::make()->title('Settlement account updated')->body('The partner has been told.')->success()->send();
                    }),

                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verify this destination')
                    ->modalDescription(fn (PartnerPayoutAccount $r): string => 'Confirm ' . $r->summaryLine()
                        . ' belongs to ' . ($r->partner?->name ?: 'this partner') . '.')
                    ->visible(fn (PartnerPayoutAccount $r): bool => ! $r->isVerified() && PayoutAccountEditor::staffMayVerify(auth()->user()))
                    ->action(function (PartnerPayoutAccount $r): void {
                        app(PayoutAccountEditor::class)->verify($r, auth()->user());
                        Notification::make()->title('Destination verified')->success()->send();
                    }),

                Action::make('unverify')
                    ->label('Remove verification')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (PartnerPayoutAccount $r): bool => $r->isVerified() && PayoutAccountEditor::staffMayVerify(auth()->user()))
                    ->action(function (PartnerPayoutAccount $r): void {
                        app(PayoutAccountEditor::class)->unverify($r, auth()->user());
                        Notification::make()->title('Verification removed')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('No settlement accounts yet')
            ->emptyStateDescription('Partners add these in the app or /partner → Payouts, or use "Add for a partner" above.');
    }

    /** "Add for a partner": for partners who haven't entered a destination themselves. */
    public static function addAction(): Action
    {
        $u = auth()->user();

        return Action::make('addForPartner')
            ->label('Add for a partner')
            ->icon('heroicon-m-plus')
            ->modalHeading('Add a settlement account')
            ->modalDescription('The partner is told on their phone. Verify it separately once checked.')
            ->schema([
                Select::make('partner_id')
                    ->label('Partner')
                    ->required()
                    ->searchable()
                    ->options(function () use ($u): array {
                        $q = User::query()->whereNull('parent_partner_id')
                            ->where(fn ($w) => $w->whereRaw('UPPER(role) = ?', ['PARTNER'])->orWhereHas('roles', fn ($r) => $r->where('name', 'PARTNER')))
                            ->whereNotIn('id', PartnerPayoutAccount::query()->pluck('partner_id'));
                        if ($u !== null && ! $u->canManage('finance')) {
                            $q->whereIn('id', PayoutAccountEditor::managedPartnerIds($u));
                        }

                        return $q->orderBy('name')->limit(200)->pluck('name', 'id')->all();
                    }),
                ...self::destinationFields(),
            ])
            ->action(function (array $data): void {
                $partnerId = (int) $data['partner_id'];
                abort_unless(PayoutAccountEditor::staffMayEdit(auth()->user(), $partnerId), 403);
                app(PayoutAccountEditor::class)->save($partnerId, $data, auth()->user());
                Notification::make()->title('Settlement account added')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerPayoutAccounts::route('/'),
        ];
    }
}
