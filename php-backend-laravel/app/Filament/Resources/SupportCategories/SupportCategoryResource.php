<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportCategories;

use App\Filament\Resources\SupportCategories\Pages\CreateSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\EditSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\ListSupportCategories;
use App\Models\SupportCategory;
use BackedEnum;
use Filament\Forms\Components\Select;
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
            TextInput::make('subtitle')
                ->maxLength(255)
                ->helperText('One line of examples under the label — this is what stops users picking the wrong topic. e.g. "Failed payment, refund status".'),
            Select::make('icon_key')
                ->label('Icon')
                ->required()
                ->default('chat')
                ->options(SupportCategory::ICON_KEYS)
                ->helperText('Older app builds fall back to the chat bubble for icons they do not know yet.'),
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
                TextColumn::make('label')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (SupportCategory $r): ?string => $r->subtitle),
                TextColumn::make('icon_key')
                    ->label('Icon')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => SupportCategory::ICON_KEYS[$state] ?? $state),
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
