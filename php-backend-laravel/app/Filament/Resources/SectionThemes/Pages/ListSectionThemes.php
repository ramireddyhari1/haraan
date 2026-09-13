<?php

namespace App\Filament\Resources\SectionThemes\Pages;

use App\Filament\Resources\SectionThemes\SectionThemeResource;
use App\Models\SectionTheme;
use App\Support\SectionThemeJson;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListSectionThemes extends ListRecords
{
    protected static string $resource = SectionThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importJson')
                ->label('Import JSON')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import a theme file')
                ->modalDescription('Upload a .json theme exported from here (or written by hand in the same format). It is created as a new campaign theme you can review before it goes live.')
                ->schema([
                    FileUpload::make('file')
                        ->label('Theme file (.json)')
                        ->acceptedFileTypes(['application/json', 'text/plain'])
                        ->maxSize(512)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;
                    $contents = $file instanceof TemporaryUploadedFile ? (string) $file->get() : '';

                    try {
                        $theme = SectionTheme::create(SectionThemeJson::toAttributes($contents));
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Could not import this file')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Theme imported')->body($theme->campaign_name)->success()->send();
                    $this->redirect(SectionThemeResource::getUrl('edit', ['record' => $theme]));
                }),
            CreateAction::make(),
        ];
    }
}
