<?php

declare(strict_types=1);

namespace App\Filament\Resources\Translations;

use App\Filament\Resources\Translations\Pages\CreateTranslation;
use App\Filament\Resources\Translations\Pages\EditTranslation;
use App\Filament\Resources\Translations\Pages\ListTranslations;
use App\Models\Translation;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $navigationLabel = 'Localization';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->helperText('Dot key the app looks up, e.g. match_detail.start_scoring.'),
            TextInput::make('group')
                ->helperText('Optional label to organize keys, e.g. match_detail.'),
            Select::make('locale')
                ->options(array_combine(Translation::LOCALES, Translation::LOCALES))
                ->required()
                ->default(Translation::FALLBACK),
            Textarea::make('value')->columnSpanFull()->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('group')->badge()->placeholder('—')->sortable(),
                TextColumn::make('key')->searchable()->sortable()->copyable(),
                TextColumn::make('locale')->badge()->sortable(),
                TextInputColumn::make('value')
                    ->label('Value')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('locale')->options(array_combine(Translation::LOCALES, Translation::LOCALES)),
                SelectFilter::make('group')
                    ->options(fn (): array => Translation::query()
                        ->whereNotNull('group')->distinct()->pluck('group', 'group')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranslations::route('/'),
            'create' => CreateTranslation::route('/create'),
            'edit' => EditTranslation::route('/{record}/edit'),
        ];
    }
}
