<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories;

use App\Filament\Resources\SupportCategories\Pages\CreateSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\EditSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\ListSupportCategories;
use App\Models\SupportCategory;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * The issue topics the app shows before a support chat starts. Editing these
 * changes the app's picker immediately — no release needed.
 */
class SupportCategoryResource extends Resource
{
    protected static ?string $model = SupportCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Support topics';

    protected static ?string $modelLabel = 'support topic';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'label';

    /** Same audience as the conversations themselves — super-admins and ops. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->helperText('What the user taps, e.g. "Payments & refunds".'),
            TextInput::make('icon')
                ->label('Icon')
                ->maxLength(16)
                ->helperText('A single emoji, shown on the card in the app. Leave blank for none.'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first.'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Off hides the topic from the app. Existing threads keep it.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')->label('')->size('lg'),
                TextColumn::make('label')->searchable()->weight('bold'),
                TextColumn::make('threads_count')
                    ->label('Conversations')
                    ->counts('threads')
                    ->sortable(),
                ToggleColumn::make('is_active')->label('Active'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSupportCategories::route('/'),
            'create' => CreateSupportCategory::route('/create'),
            'edit'   => EditSupportCategory::route('/{record}/edit'),
        ];
    }
}
