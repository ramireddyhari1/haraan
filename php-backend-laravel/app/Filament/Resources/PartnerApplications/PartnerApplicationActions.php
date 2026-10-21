<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerApplications;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\AdminAction;
use App\Models\PartnerApplication;
use App\Models\PartnerPayoutAccount;
use App\Services\PartnerApplicationVenueBuilder;
use App\Support\PartnerAccountResolver;
use App\Support\PartnerLane;
use App\Support\PayoutAccountEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/** The review steps, shared by the table rows and the application's own page. */
final class PartnerApplicationActions
{
    public static function call(): Action
    {
        return Action::make('call')
            ->label('Call')
            ->icon('heroicon-m-phone')
            ->color('gray')
            ->url(fn (PartnerApplication $record): string => 'tel:' . $record->phone);
    }

    public static function whatsapp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon('heroicon-m-chat-bubble-left-right')
            ->color('gray')
            ->url(fn (PartnerApplication $record): string => 'https://wa.me/' . ltrim($record->phone, '+'), shouldOpenInNewTab: true);
    }

    public static function markContacted(): Action
    {
        return Action::make('contacted')
            ->label('Mark contacted')
            ->icon('heroicon-m-check')
            ->color('info')
            ->visible(fn (PartnerApplication $record): bool => $record->status === PartnerApplication::STATUS_NEW)
            ->schema([
                Textarea::make('admin_notes')->label('What came of the call')->rows(3)->maxLength(2000),
            ])
            ->fillForm(fn (PartnerApplication $record): array => ['admin_notes' => $record->admin_notes])
            ->action(function (PartnerApplication $record, array $data): void {
                $record->forceFill([
                    'status' => PartnerApplication::STATUS_CONTACTED,
                    'contacted_at' => now(),
                    'reviewed_by' => auth()->id(),
                    'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                ])->save();

                AdminAction::log('partner_application.contacted', ['reference' => $record->reference], $record);
                Notification::make()->success()->title('Marked as contacted')->send();
            });
    }

    /**
     * Create the partner account from the application. Goes through
     * {@see PartnerAccountResolver} like every other partner create, so an email that
     * already belongs to a member upgrades that member instead of duplicating them.
     */
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Create partner')
            ->icon('heroicon-m-user-plus')
            ->color('primary')
            ->visible(fn (PartnerApplication $record): bool => $record->partner_id === null
                && $record->status !== PartnerApplication::STATUS_REJECTED)
            ->modalHeading(fn (PartnerApplication $record): string => 'Create a partner for ' . $record->business_name)
            ->modalDescription(fn (PartnerApplication $record): string => 'Their console sign-in uses this email or phone.'
                . ($record->hasPayoutDetails() ? ' The bank / UPI details they gave become their settlement account, unverified until finance checks them.' : ''))
            ->modalSubmitActionLabel('Create partner')
            ->schema([
                TextInput::make('name')->label('Partner name')->required()->maxLength(120),
                TextInput::make('email')->label('Sign-in email')->email()->required()->maxLength(160)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (($reason = PartnerAccountResolver::blockReason(PartnerAccountResolver::findByEmail((string) $value))) !== null) {
                            $fail($reason);
                        }
                    })
                    ->helperText(fn (?string $state): ?string => PartnerAccountResolver::isUpgrade($state)
                        ? 'This email already has a member account — it will be upgraded, keeping its bookings and history.'
                        : null)
                    ->live(onBlur: true),
                TextInput::make('phone')->label('Phone')->tel()->maxLength(20),
                Select::make('partner_type')->label('Partner type')->options(PartnerLane::TYPE_LABELS)->required()->native(false),
                Textarea::make('admin_notes')->label('Team notes')->rows(2)->maxLength(2000),
            ])
            ->fillForm(fn (PartnerApplication $record): array => [
                'name' => $record->business_name,
                'email' => $record->email,
                'phone' => $record->phone,
                'partner_type' => $record->partnerType(),
                'admin_notes' => $record->admin_notes,
            ])
            ->action(function (PartnerApplication $record, array $data, Action $action): void {
                try {
                    [$partner, $upgraded] = DB::transaction(function () use ($record, $data): array {
                        $result = PartnerAccountResolver::upgradeOrCreate([
                            'name' => $data['name'],
                            'email' => mb_strtolower(trim($data['email'])),
                            'phone' => $data['phone'] ?: $record->phone,
                            'partner_type' => $data['partner_type'],
                            'status' => 'active',
                        ]);

                        // Their payout details become the settlement account — through the one
                        // write path, so the change trail and "unverified" state apply.
                        if ($record->hasPayoutDetails() && ! PartnerPayoutAccount::where('partner_id', $result[0]->id)->exists()) {
                            $bank = $record->payout_method === 'bank' && filled($record->account_number);
                            app(PayoutAccountEditor::class)->save($result[0]->id, [
                                'method' => $bank ? 'bank' : 'upi',
                                'account_holder' => $record->account_holder ?: $data['name'],
                                'bank_name' => $record->bank_name,
                                'account_number' => $record->account_number,
                                'ifsc_code' => $record->ifsc_code,
                                'upi_vpa' => $record->upi_vpa,
                            ], auth()->user());
                        }

                        $record->forceFill([
                            'status' => PartnerApplication::STATUS_APPROVED,
                            'partner_id' => $result[0]->id,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'contacted_at' => $record->contacted_at ?? now(),
                            'admin_notes' => $data['admin_notes'] ?? $record->admin_notes,
                        ])->save();

                        return $result;
                    });
                } catch (\RuntimeException $e) {
                    Notification::make()->danger()->title('Could not create the partner')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                AdminAction::log('partner_application.approved', [
                    'reference' => $record->reference,
                    'partner_id' => $partner->id,
                    'upgraded_member' => $upgraded,
                ], $record);

                Notification::make()
                    ->success()
                    ->title($upgraded ? 'Existing member upgraded to a partner' : 'Partner created')
                    ->body($record->hasPlace() ? 'Next: Create venue builds their draft venue from this application.' : 'They can sign in and add events from their console.')
                    ->actions([
                        Action::make('open')->label('Open partner')->url(PartnerResource::getUrl('view', ['record' => $partner])),
                    ])
                    ->send();
            });
    }

    /**
     * The draft venue, filled from the application the way the /control venue form
     * would be. Opens the venue page so the admin can check it and put it live.
     */
    public static function createVenue(): Action
    {
        return Action::make('createVenue')
            ->label('Create venue')
            ->icon('heroicon-m-building-office-2')
            ->color('primary')
            ->visible(fn (PartnerApplication $record): bool => $record->hasPlace()
                && $record->partner_id !== null
                && $record->venue_id === null)
            ->requiresConfirmation()
            ->modalHeading(fn (PartnerApplication $record): string => 'Create ' . $record->business_name . ' as a draft venue')
            ->modalDescription(fn (PartnerApplication $record): string => 'Name, location, ' . count($record->courtList()) . ' '
                . (count($record->courtList()) === 1 ? 'court' : 'courts') . ', hours, prices, '
                . count((array) $record->images) . ' photos, amenities and rules are copied in. '
                . 'The venue stays a draft — check it, then set it Live from the venue page.')
            ->modalSubmitActionLabel('Create draft venue')
            ->action(function (PartnerApplication $record, Action $action) {
                try {
                    $venue = app(PartnerApplicationVenueBuilder::class)->build($record);
                } catch (\RuntimeException $e) {
                    Notification::make()->danger()->title('Could not create the venue')->body($e->getMessage())->send();
                    $action->halt();

                    return null;
                }

                AdminAction::log('partner_application.venue_created', [
                    'reference' => $record->reference,
                    'venue_id' => $venue->id,
                ], $record);

                Notification::make()->success()->title('Draft venue created')
                    ->body('Check the details and photos, then set the status to Live.')->send();

                return redirect(VenueResource::getUrl('edit', ['record' => $venue]));
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Not a fit')
            ->icon('heroicon-m-x-mark')
            ->color('gray')
            ->visible(fn (PartnerApplication $record): bool => in_array($record->status, [PartnerApplication::STATUS_NEW, PartnerApplication::STATUS_CONTACTED], true))
            ->schema([
                Textarea::make('admin_notes')->label('Why')->required()->rows(3)->maxLength(2000),
            ])
            ->fillForm(fn (PartnerApplication $record): array => ['admin_notes' => $record->admin_notes])
            ->action(function (PartnerApplication $record, array $data): void {
                $record->forceFill([
                    'status' => PartnerApplication::STATUS_REJECTED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'admin_notes' => $data['admin_notes'],
                ])->save();

                AdminAction::log('partner_application.rejected', ['reference' => $record->reference], $record);
                Notification::make()->title('Application closed')->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (PartnerApplication $record): bool => $record->status === PartnerApplication::STATUS_REJECTED)
            ->requiresConfirmation()
            ->action(function (PartnerApplication $record): void {
                $record->forceFill(['status' => $record->contacted_at ? PartnerApplication::STATUS_CONTACTED : PartnerApplication::STATUS_NEW])->save();
                AdminAction::log('partner_application.reopened', ['reference' => $record->reference], $record);
            });
    }

    public static function notes(): Action
    {
        return Action::make('notes')
            ->label('Notes')
            ->icon('heroicon-m-pencil-square')
            ->color('gray')
            ->schema([
                Textarea::make('admin_notes')->label('Team notes')->rows(5)->maxLength(2000),
            ])
            ->fillForm(fn (PartnerApplication $record): array => ['admin_notes' => $record->admin_notes])
            ->action(function (PartnerApplication $record, array $data): void {
                $record->forceFill(['admin_notes' => $data['admin_notes']])->save();
                Notification::make()->success()->title('Notes saved')->send();
            });
    }
}
