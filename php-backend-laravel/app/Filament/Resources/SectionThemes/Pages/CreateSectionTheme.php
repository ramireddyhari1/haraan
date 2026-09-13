<?php

namespace App\Filament\Resources\SectionThemes\Pages;

use App\Filament\Resources\SectionThemes\SectionThemeResource;
use App\Support\SectionThemeJson;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateSectionTheme extends CreateRecord
{
    protected static string $resource = SectionThemeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SectionThemeResource::foldDecoration($data);
    }

    /**
     * One "Import JSON" that understands both .json files an admin might have: a theme
     * settings file fills the form, a Lottie animation becomes the decoration. Nothing is
     * saved until they review it and press Create.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('importJson')
                ->label('Import JSON')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import a .json file')
                ->modalDescription('A theme file (from "Export JSON") fills in this whole form. A Lottie animation is checked and attached as the decoration. You can review everything before pressing Create.')
                ->modalSubmitActionLabel('Import')
                ->schema([
                    FileUpload::make('file')
                        ->label('Theme file or Lottie animation (.json)')
                        ->acceptedFileTypes(['application/json', 'text/plain'])
                        ->maxSize(4096)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;
                    $contents = $file instanceof TemporaryUploadedFile ? (string) $file->get() : '';

                    match (SectionThemeJson::kind($contents)) {
                        SectionThemeJson::KIND_THEME => $this->fillFromThemeFile($contents),
                        SectionThemeJson::KIND_LOTTIE => $this->attachLottie($contents),
                        default => $this->importFailed('This file is neither a Haraan theme file nor a Lottie animation. Check it opens as valid JSON.'),
                    };
                }),
        ];
    }

    private function fillFromThemeFile(string $contents): void
    {
        try {
            $attrs = SectionThemeJson::toAttributes($contents);
        } catch (ValidationException $e) {
            $this->importFailed(collect($e->errors())->flatten()->first());

            return;
        }

        $link = $attrs['decoration'] ?? null;
        $this->form->fill(array_merge($this->form->getRawState(), [
            'section' => $attrs['section'],
            'campaign_name' => $attrs['campaign_name'],
            'accent_primary' => $attrs['accent_primary'],
            'accent_deep' => $attrs['accent_deep'],
            'accent_tint' => $attrs['accent_tint'],
            'on_primary' => $attrs['on_primary'],
            // Form state is in the app timezone (UTC); the pickers display it in IST.
            'starts_at' => $attrs['starts_at']->format('Y-m-d H:i:s'),
            'ends_at' => $attrs['ends_at']->format('Y-m-d H:i:s'),
            'priority' => $attrs['priority'],
            'is_active' => $attrs['is_active'],
            'decoration' => null,
            'decoration_url' => $link,
        ]));

        Notification::make()
            ->title('Form filled from the theme file')
            ->body('Review it, then press Create.')
            ->success()
            ->send();
    }

    private function attachLottie(string $contents): void
    {
        if (($problem = SectionThemeJson::lottieProblem($contents)) !== null) {
            $this->importFailed($problem);

            return;
        }

        $path = 'section-themes/'.Str::ulid().'.json';
        Storage::disk('public')->put($path, $contents, 'public');

        $this->form->fill(array_merge($this->form->getRawState(), [
            'decoration' => $path,
            'decoration_url' => null,
        ]));

        Notification::make()
            ->title('Lottie attached as the decoration')
            ->body('Fill in the lane, name, dates and colours, then press Create.')
            ->success()
            ->send();
    }

    private function importFailed(string $reason): void
    {
        Notification::make()
            ->title('Could not import this file')
            ->body($reason)
            ->danger()
            ->persistent()
            ->send();
    }
}
