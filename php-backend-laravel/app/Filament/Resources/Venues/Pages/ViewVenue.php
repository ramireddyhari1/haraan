<?php

declare(strict_types=1);

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use App\Filament\Resources\Venues\Widgets\VenueAnalyticsStatsWidget;
use App\Filament\Resources\Venues\Widgets\VenueCommandHeroWidget;
use App\Models\AdminAction;
use App\Models\Notification as InboxNotification;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

/**
 * Venue 360 — one pane of glass for a single listing.
 *
 * The header is arranged the way an operator actually works: the things you do
 * daily are plain buttons, the things you do occasionally are behind a menu, and
 * the things you can only do once are behind a menu of their own that is labelled
 * Danger zone and coloured like one. Nothing destructive sits next to anything
 * routine, and every destructive action states what it will break before it runs.
 *
 * Each action below writes something real: a column, a slot regeneration, a bell
 * notification, or an audit row. There are no placeholder buttons.
 */
class ViewVenue extends ViewRecord
{
    protected static string $resource = VenueResource::class;

    /** Statuses that mean a booking is live and would be broken by a teardown. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    public function getTitle(): string
    {
        /** @var Venue $record */
        $record = $this->getRecord();

        return $record->branchName() . ' · Venue 360';
    }

    public function getSubheading(): ?string
    {
        /** @var Venue $record */
        $record = $this->getRecord();

        return implode(' · ', array_filter([
            $record->name,
            $record->city ?: $record->location,
            $record->displayHours() ?: null,
        ])) ?: null;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VenueCommandHeroWidget::class,
            VenueAnalyticsStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    // Both header widgets declare `public ?Venue $record` and are filled by
    // InteractsWithRecord::getWidgetData(); the infolist comes from the resource.

    protected function getHeaderActions(): array
    {
        return [
            $this->publishAction(),
            $this->unpublishAction(),

            EditAction::make(),

            $this->regenerateSlotsAction(),

            $this->notifyOwnerAction(),

            ActionGroup::make([
                Action::make('jumpBookings')
                    ->label('Open in Bookings & payments')
                    ->icon('heroicon-m-banknotes')
                    ->url(fn (): string => \App\Filament\Resources\VenueBookings\VenueBookingResource::getUrl('index', [
                        'tableFilters' => ['venue_id' => ['value' => $this->getRecord()->getKey()]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpBlocks')
                    ->label('Open in Blocked time')
                    ->icon('heroicon-m-no-symbol')
                    ->url(fn (): string => \App\Filament\Resources\VenueBlocks\VenueBlockResource::getUrl('index', [
                        'tableFilters' => ['venue_id' => ['value' => $this->getRecord()->getKey()]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpAnalytics')
                    ->label('Full analytics')
                    ->icon('heroicon-m-chart-bar')
                    ->url(fn (): string => VenueAnalytics::getUrl(['record' => $this->getRecord()->getKey()]))
                    ->visible(fn (): bool => VenueAnalytics::canAccess(['record' => $this->getRecord()])),
            ])
                ->label('Go to')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray'),

            $this->dangerZone(),
        ];
    }

    // -------------------------------------------------------------------------
    //  Routine actions
    // -------------------------------------------------------------------------

    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish / Go Live')
            ->icon('heroicon-m-globe-alt')
            ->color('success')
            ->visible(fn (): bool => ! $this->getRecord()->isPublished())
            ->action(function (): void {
                /** @var Venue $record */
                $record = $this->getRecord();
                $errors = $record->readinessErrors();
                if (! empty($errors)) {
                    Notification::make()
                        ->title('Cannot publish venue — Setup incomplete')
                        ->body("Please fix the following issues before publishing:\n• " . implode("\n• ", $errors))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $record->publish();

                Notification::make()
                    ->title('Venue Published!')
                    ->body('This venue is now live, visible to users, and open for bookings.')
                    ->success()
                    ->send();
            });
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish / Move to Draft')
            ->icon('heroicon-m-eye-slash')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Unpublish venue?')
            ->modalDescription('This venue will be removed from the mobile app and public web search immediately. No new bookings can be made.')
            ->modalSubmitActionLabel('Unpublish to Draft')
            ->visible(fn (): bool => $this->getRecord()->isPublished())
            ->action(function (): void {
                /** @var Venue $record */
                $record = $this->getRecord();
                $record->unpublish();

                Notification::make()
                    ->title('Venue Unpublished')
                    ->body('This venue is now in draft mode and hidden from public search.')
                    ->info()
                    ->send();
            });
    }

    /**
     * Rebuild the slot grid from the structured hours. This is the same call
     * EditVenue makes after a save; it is exposed here because hours and slots
     * drift whenever slots are edited by hand, and until now the only way to
     * resync them was to open the form and press Save on an unchanged record.
     */
    private function regenerateSlotsAction(): Action
    {
        return Action::make('regenerateSlots')
            ->label('Rebuild slots from hours')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Rebuild the slot grid?')
            ->modalDescription('Every existing slot for this venue is deleted and regenerated from the operating hours, stepped by the slot length. Per-slot prices, capacities and sport restrictions set by hand will be lost. Bookings already taken are not affected.')
            ->modalSubmitActionLabel('Rebuild slots')
            ->visible(fn (): bool => auth()->user()?->hasPartnerPermission('pricing') ?? false)
            ->disabled(fn (): bool => ! is_array($this->getRecord()->hours_json) || $this->getRecord()->hours_json === [])
            ->action(function (): void {
                /** @var Venue $record */
                $record = $this->getRecord();

                $before = $record->slots()->count();
                $record->regenerateSlotsFromHours();
                $record->update(['hours' => $record->displayHours()]);
                $after = $record->slots()->count();

                AdminAction::log('venue.slots_regenerated', [
                    'slots_before' => $before,
                    'slots_after'  => $after,
                    'slot_minutes' => (int) ($record->slot_minutes ?: 60),
                ], $record);

                Notification::make()
                    ->title('Slot grid rebuilt')
                    ->body($before . ' slots replaced with ' . $after . ' generated from the operating hours.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Write a bell-inbox notification addressed at the venue's owner. Uses the
     * existing single-user audience, so it fans out to their devices through the
     * same push job as every other notification — no separate delivery path.
     */
    private function notifyOwnerAction(): Action
    {
        return Action::make('notifyOwner')
            ->label('Notify owner')
            ->icon('heroicon-m-bell-alert')
            ->color('gray')
            ->visible(fn (): bool => $this->getRecord()->partner_id !== null)
            ->modalHeading(fn (): string => 'Notify ' . ($this->getRecord()->partner?->name ?? 'the owner'))
            ->modalDescription('Delivered to their Haraan bell inbox and pushed to their devices.')
            ->modalSubmitActionLabel('Send notification')
            ->schema([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(120)
                    ->default(fn (): string => 'About ' . $this->getRecord()->branchName()),

                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(4)
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                /** @var Venue $record */
                $record = $this->getRecord();

                InboxNotification::create([
                    'title'          => $data['title'],
                    'body'           => $data['body'],
                    'audience_type'  => 'user',
                    'audience_value' => (string) $record->partner_id,
                    'status'         => 'sent',
                    'source'         => 'composer',
                    'created_by'     => auth()->id(),
                ]);

                AdminAction::log('venue.owner_notified', [
                    'partner_id' => $record->partner_id,
                    'title'      => $data['title'],
                ], $record);

                Notification::make()
                    ->title('Notification sent to the owner')
                    ->success()
                    ->send();
            });
    }

    // -------------------------------------------------------------------------
    //  Danger zone
    // -------------------------------------------------------------------------

    /**
     * Kept apart from everything else, and every item states its blast radius.
     * Taking a venue off sale is reversible and says so; removing the owner and
     * deleting the listing are not, and say that instead.
     */
    private function dangerZone(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('toggleBookable')
                ->label(fn (): string => $this->getRecord()->is_bookable ? 'Stop taking bookings' : 'Resume taking bookings')
                ->icon(fn (): string => $this->getRecord()->is_bookable ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle')
                ->color(fn (): string => $this->getRecord()->is_bookable ? 'danger' : 'success')
                ->visible(fn (): bool => VenueResource::canEdit($this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->getRecord()->is_bookable ? 'Stop taking bookings?' : 'Resume taking bookings?')
                ->modalDescription(fn (): string => $this->getRecord()->is_bookable
                    ? 'The listing stays visible in the app and on the site, but the book button disappears. Bookings already taken are untouched. Reversible.'
                    : 'The book button returns immediately across the app, the site and the API.')
                ->action(fn () => $this->flipFlag('is_bookable')),

            Action::make('toggleActive')
                ->label(fn (): string => $this->getRecord()->isPublished() ? 'Unpublish listing' : 'Publish listing')
                ->icon(fn (): string => $this->getRecord()->isPublished() ? 'heroicon-m-eye-slash' : 'heroicon-m-globe-alt')
                ->color(fn (): string => $this->getRecord()->isPublished() ? 'danger' : 'success')
                ->visible(fn (): bool => VenueResource::canEdit($this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->getRecord()->isPublished() ? 'Unpublish this listing?' : 'Publish this listing?')
                ->modalDescription(fn (): string => $this->getRecord()->isPublished()
                    ? 'The venue disappears from the app, the site, search and every feed. Customers holding a booking keep it, but cannot open the venue page. Reversible.'
                    : 'The venue returns to the app, the site and search.')
                ->action(function (): void {
                    /** @var Venue $record */
                    $record = $this->getRecord();
                    if ($record->isPublished()) {
                        $record->unpublish();
                        Notification::make()
                            ->title('Listing unpublished')
                            ->body('The venue is now in draft mode and hidden from users.')
                            ->info()
                            ->send();
                    } else {
                        $errors = $record->readinessErrors();
                        if (! empty($errors)) {
                            Notification::make()
                                ->title('Cannot publish venue — Setup incomplete')
                                ->body("Please fix the following issues before publishing:\n• " . implode("\n• ", $errors))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $record->publish();
                        Notification::make()
                            ->title('Listing published')
                            ->body('The venue is now live and bookable.')
                            ->success()
                            ->send();
                    }
                }),

            Action::make('unassignOwner')
                ->label('Remove owner')
                ->icon('heroicon-m-user-minus')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->partner_id !== null
                    && (auth()->user()?->isSuperAdmin() ?? false))
                ->requiresConfirmation()
                ->modalHeading('Remove the owner from this venue?')
                ->modalDescription(fn (): string => ($this->getRecord()->partner?->name ?? 'The owner')
                    . ' loses access to this venue in the partner console immediately — its bookings, its desk and its earnings. Staff assigned to this branch lose it too. Settlements already recorded are not affected. Reassigning an owner is done from the venue form.')
                ->modalSubmitActionLabel('Remove owner')
                ->action(function (): void {
                    /** @var Venue $record */
                    $record = $this->getRecord();
                    $previous = $record->partner_id;

                    $record->partner_id = null;
                    $record->save();

                    AdminAction::log('venue.owner_removed', ['previous_partner_id' => $previous], $record);

                    Notification::make()
                        ->title('Owner removed')
                        ->body('This venue is now unassigned and cannot be managed from the partner console.')
                        ->warning()
                        ->send();
                }),

            Action::make('deleteVenue')
                ->label('Delete venue')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->visible(fn (): bool => VenueResource::canDelete($this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Delete this venue permanently?')
                ->modalDescription(fn (): string => $this->deleteBlockReason()
                    ?? 'This removes the listing, its courts, slots, blocks and reviews. Bookings already taken keep pointing at a venue that no longer exists. This cannot be undone.')
                ->modalSubmitActionLabel('Delete permanently')
                ->disabled(fn (): bool => $this->deleteBlockReason() !== null)
                ->action(function () {
                    /** @var Venue $record */
                    $record = $this->getRecord();

                    if ($this->deleteBlockReason() !== null) {
                        Notification::make()
                            ->title('Deletion refused')
                            ->body($this->deleteBlockReason())
                            ->danger()
                            ->send();

                        return null;
                    }

                    AdminAction::log('venue.deleted', [
                        'name' => $record->name,
                        'city' => $record->city,
                    ], $record);

                    $record->delete();

                    Notification::make()
                        ->title('Venue deleted')
                        ->success()
                        ->send();

                    return redirect(VenueResource::getUrl('index'));
                }),
        ])
            ->label('Danger zone')
            ->icon('heroicon-m-exclamation-triangle')
            ->color('danger')
            ->button();
    }

    /**
     * Why this venue must not be deleted right now, or null when it is safe.
     * A venue with live bookings ahead of it cannot vanish from under the people
     * holding them — take it off sale instead and let the bookings run out.
     */
    private function deleteBlockReason(): ?string
    {
        $upcoming = (int) $this->getRecord()->bookings()
            ->whereIn(DB::raw('lower(status)'), self::PAID)
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->count();

        if ($upcoming === 0) {
            return null;
        }

        return 'This venue has ' . $upcoming . ' upcoming confirmed '
            . str('booking')->plural($upcoming)
            . '. Deleting it would strand them. Stop taking bookings instead, and delete once the last one has been played.';
    }

    /** Flip a boolean column, log it, and say what changed. */
    private function flipFlag(string $column): void
    {
        /** @var Venue $record */
        $record = $this->getRecord();

        $record->{$column} = ! $record->{$column};
        $record->save();

        // The venue model already audits is_active / is_bookable changes itself,
        // so nothing extra is logged here — see Venue::$auditedAttributes.

        Notification::make()
            ->title(match ([$column, (bool) $record->{$column}]) {
                ['is_bookable', true]  => 'Bookings resumed',
                ['is_bookable', false] => 'Bookings stopped',
                ['is_active', true]    => 'Listing published',
                default                => 'Listing unpublished',
            })
            ->success()
            ->send();
    }
}
