<?php

namespace App\Filament\Resources\LoginPosters\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LoginPosterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Keeps every row this resource creates on the login_poster placement so the
                // Ads list and the /api/login-posters endpoint stay cleanly separated.
                Hidden::make('placement')->default('login_poster'),

                Section::make('Poster image')
                    ->description('Upload an image OR paste a link to one already hosted online. Tall portrait art works best — it fills the top of the login screen behind the sign-in card.')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Upload image')
                            ->image()
                            // Explicit types (not the "image/*" wildcard, which some setups reject
                            // server-side) so a bad file gives a clear message.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(15360) // 15 MB — server allows 16M; keep a friendly margin.
                            ->imageEditor()
                            ->disk('public')
                            ->directory('login-posters')
                            ->visibility('public')
                            ->helperText('JPG, PNG or WebP, up to 15 MB.'),

                        TextInput::make('image_url')
                            ->label('…or paste an image URL')
                            ->url()
                            ->placeholder('https://example.com/poster.jpg')
                            ->helperText('Use this instead of uploading — e.g. a link from your storage/CDN. Ignored if you upload a file above.')
                            // Not a real column; the Create/Edit pages fold it into `image`.
                            ->dehydrated(true),
                    ]),

                TextInput::make('title')
                    ->maxLength(255)
                    ->helperText('Optional — not shown on the login screen, just a label so you can tell posters apart here.'),

                TextInput::make('subtitle')
                    ->maxLength(255),

                TextInput::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers show first in the carousel.'),

                Toggle::make('is_active')
                    ->label('Live')
                    ->default(true)
                    ->helperText('Off = hidden from the app without deleting it.'),
            ]);
    }
}
