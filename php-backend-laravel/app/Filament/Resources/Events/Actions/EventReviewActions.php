<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Actions;

use App\Models\AdminAction;
use App\Models\Event;
use App\Models\Notification as InboxNotification;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Reviewing a host's submission. A partner-created event lands as "pending" (CreateEvent)
 * and is hidden until Haraan publishes it; reviewing used to mean opening the whole
 * wizard and finding the Status select, and the host was never told the outcome.
 *
 * Approve publishes it; Send back returns it to draft with a reason. Both tell the host
 * in their bell inbox and write the audit log. /control only — partners don't review.
 */
final class EventReviewActions
{
    public static function approve(string $name = 'approveEvent'): Action
    {
        return Action::make($name)
            ->label('Approve & publish')
            ->icon('heroicon-m-check-badge')
            ->color('primary')
            ->visible(fn (Event $record): bool => self::canReview($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Event $record): string => 'Publish “' . $record->title . '”?')
            ->modalDescription(function (Event $record): string {
                $notes = self::warnings($record);

                return 'It goes live in the app and on the site straight away, and the host is told.'
                    . ($notes === [] ? '' : "\n\nWorth a look first: " . implode(' · ', $notes) . '.');
            })
            ->modalSubmitActionLabel('Approve & publish')
            ->action(function (Event $record): void {
                if ($record->hasFinished()) {
                    Notification::make()
                        ->title('This event has already finished')
                        ->body('Change its date before publishing — a finished event can\'t be sold.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->forceFill(['status' => 'published'])->save();

                self::tellHost($record, 'Your event is live', "“{$record->title}” was approved and is now on sale on Haraan.");
                AdminAction::log('event.approved', ['partner_id' => $record->partner_id], $record);

                Notification::make()->title('Published — the host has been told')->success()->send();
            });
    }

    public static function sendBack(string $name = 'sendBackEvent'): Action
    {
        return Action::make($name)
            ->label('Send back')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Event $record): bool => self::canReview($record))
            ->modalHeading(fn (Event $record): string => 'Send “' . $record->title . '” back to the host')
            ->modalDescription('It returns to draft. The host sees your note in their bell inbox, fixes it and submits again.')
            ->schema([
                Textarea::make('reason')
                    ->label('What needs changing')
                    ->placeholder('e.g. The poster is blurry, and the venue address is missing a PIN code.')
                    ->required()
                    ->rows(4)
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Send back')
            ->action(function (Event $record, array $data): void {
                $record->forceFill(['status' => 'draft'])->save();

                self::tellHost($record, 'Your event needs a change', "“{$record->title}” wasn't published yet: " . trim($data['reason']));
                AdminAction::log('event.sent_back', ['partner_id' => $record->partner_id, 'reason' => $data['reason']], $record);

                Notification::make()->title('Sent back to the host')->success()->send();
            });
    }

    private static function canReview(Event $record): bool
    {
        return $record->isPendingReview()
            && Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->canManage('events') ?? false);
    }

    /** Things that won't stop publishing but make a listing look broken. */
    private static function warnings(Event $record): array
    {
        $notes = [];
        if ($record->heroImageUrl() === null) {
            $notes[] = 'no poster';
        }
        if (trim((string) ($record->venue ?: $record->location)) === '') {
            $notes[] = 'no venue';
        }
        if ($record->fromPrice() <= 0) {
            $notes[] = 'it will show as free';
        }

        return $notes;
    }

    private static function tellHost(Event $record, string $title, string $body): void
    {
        if ($record->partner_id === null) {
            return;
        }

        InboxNotification::create([
            'title'          => $title,
            'body'           => $body,
            'audience_type'  => 'user',
            'audience_value' => (string) $record->partner_id,
            'status'         => 'sent',
            'source'         => 'composer',
            'created_by'     => auth()->id(),
        ]);
    }
}
