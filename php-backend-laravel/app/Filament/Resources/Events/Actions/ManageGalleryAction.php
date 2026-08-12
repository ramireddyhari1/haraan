<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Actions;

use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Per-event "Gallery" quick-manage: opens a modal to add/reorder/remove the
 * showcase photos for one event and saves straight to its `gallery` column —
 * the same photos the event detail page renders in its Gallery section. Used as
 * a row action on the Events list and the Event History page so partners don't
 * have to walk the whole event wizard just to touch the gallery.
 */
class ManageGalleryAction
{
    public static function make(string $name = 'gallery'): Action
    {
        return Action::make($name)
            ->label('Gallery')
            ->icon('heroicon-m-photo')
            ->color('gray')
            ->modalHeading(fn (Event $record): string => 'Gallery — ' . $record->title)
            ->modalDescription('These photos appear in the “Gallery” section on the event’s detail page. The poster is managed separately in the event editor.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Save gallery')
            ->fillForm(fn (Event $record): array => EventForm::splitGalleryData($record->gallery))
            ->schema(EventForm::galleryFields())
            ->action(function (array $data, Event $record): void {
                $record->gallery = EventForm::mergeGalleryData($data);
                $record->save();

                Notification::make()
                    ->title('Gallery updated')
                    ->body(count($record->galleryUrls()) . ' ' . str('photo')->plural(count($record->galleryUrls())) . ' saved.')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => auth()->user()?->canManage('events') ?? false);
    }
}
