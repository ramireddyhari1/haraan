<?php

declare(strict_types=1);

namespace App\Filament\Resources\SectionThemes\Schemas;

use App\Models\SectionTheme;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SectionThemeForm
{
    /** Admins schedule in IST; the column is UTC and the API ships epoch seconds. */
    private const ADMIN_TZ = 'Asia/Kolkata';

    public static function configure(Schema $schema): Schema
    {
        $hex = 'regex:'.SectionTheme::HEX;

        return $schema
            ->components([
                Section::make('Campaign')
                    ->description('While live, the lane header (status bar, greeting, search, switch) takes these colours. With nothing live the app looks exactly as normal. Tip: "Import JSON" on the list page fills all of this from one file.')
                    ->schema([
                        Select::make('section')
                            ->label('App lane')
                            ->options(SectionTheme::SECTIONS)
                            ->required()
                            ->native(false),
                        TextInput::make('campaign_name')
                            ->label('Campaign name')
                            ->required()
                            ->maxLength(80)
                            ->helperText('For your reference and screen readers — not drawn on screen.'),
                        Toggle::make('is_active')
                            ->label('Published')
                            ->default(true)
                            ->helperText('Off hides it immediately, even inside its window.'),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('If two campaigns overlap on the same lane, the higher number wins.'),
                    ])->columns(2),

                Section::make('Window')
                    ->description('The app switches to this theme at the start and back to normal at the end — on the minute, even offline.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Starts')
                            ->timezone(self::ADMIN_TZ)
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Ends')
                            ->timezone(self::ADMIN_TZ)
                            ->seconds(false)
                            ->required()
                            ->after('starts_at'),
                    ])->columns(2),

                Section::make('Colours')
                    ->description('Only the accent is required; blanks are derived from it. Text colours are contrast-checked in the app and corrected if unreadable.')
                    ->schema([
                        ColorPicker::make('accent_deep')
                            ->label('Header colour')
                            ->rule($hex)
                            ->helperText('The dark header surface. Blank = a darker shade of the accent.'),
                        ColorPicker::make('accent_primary')
                            ->label('Accent')
                            ->required()
                            ->rule($hex)
                            ->helperText('Buttons, selected chips, prices.'),
                        ColorPicker::make('accent_tint')
                            ->label('Tint')
                            ->rule($hex)
                            ->helperText('Soft backgrounds behind accent icons. A very light shade.'),
                        ColorPicker::make('on_primary')
                            ->label('Text on accent')
                            ->rule($hex),
                    ])->columns(2),

                Section::make('Decoration')
                    ->description('Optional artwork under the switch — lights, garlands, a festive scene. A transparent PNG/WebP, or a Lottie animation (.json) for movement. Always shown whole, never cropped: the strip takes the shape of the artwork. Best between 3:1 and 4.5:1 (e.g. 2160 × 720 or 1080 × 240); taller than 5:2 is scaled down to fit.')
                    ->schema([
                        FileUpload::make('decoration')
                            ->label('Upload image or Lottie')
                            ->acceptedFileTypes(['image/png', 'image/webp', 'application/json', 'text/plain'])
                            ->maxSize(4096)
                            ->disk('public')
                            ->directory('section-themes')
                            ->visibility('public')
                            ->helperText('PNG, WebP or Lottie .json · up to 4 MB · transparent background looks best.'),
                        TextInput::make('decoration_url')
                            ->label('…or paste a link')
                            ->url()
                            ->placeholder('https://cdn.example.com/diwali-lights.json')
                            ->helperText('A hosted .png, .webp or Lottie .json. Ignored if you upload a file above.')
                            ->dehydrated(true),
                    ]),
            ]);
    }
}
