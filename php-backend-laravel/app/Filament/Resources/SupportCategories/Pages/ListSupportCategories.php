<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories\Pages;

use App\Filament\Resources\SupportCategories\SupportCategoryResource;
use App\Models\AdminAction;
use App\Models\AppSetting;
use App\Support\SupportPageCopy;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSupportCategories extends ListRecords
{
    protected static string $resource = SupportCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The rest of what haraan.app/support says, next to the topics it lists.
            Action::make('pageText')
                ->label('Support page text')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->modalHeading('Support page text')
                ->modalDescription('What people read on haraan.app/support. Saved text shows on the next page load.')
                ->fillForm(fn (): array => SupportPageCopy::all())
                ->schema(array_map(
                    fn (string $key): TextInput => TextInput::make($key)
                        ->label(SupportPageCopy::TEXTS[$key][2])
                        ->helperText(SupportPageCopy::TEXTS[$key][3])
                        ->maxLength(SupportPageCopy::TEXTS[$key][1])
                        ->required(),
                    array_keys(SupportPageCopy::TEXTS),
                ))
                ->action(function (array $data): void {
                    foreach (array_keys(SupportPageCopy::TEXTS) as $key) {
                        AppSetting::set(SupportPageCopy::storageKey($key), trim((string) ($data[$key] ?? '')), SupportPageCopy::GROUP);
                    }
                    AdminAction::log('support_page_copy.updated', ['values' => $data]);
                    Notification::make()->title('Support page text saved')->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
