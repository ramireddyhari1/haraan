<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\ManageRewardSponsors;
use App\Models\AdminAction;
use App\Models\RewardSponsor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rewards → Sponsors. Brands that fund rewards. Public profile only: there is deliberately no
 * field for an API key or password — a sponsor integration reads credentials from the server
 * environment, never from /control.
 */
class RewardSponsorResource extends Resource
{
    protected static ?string $model = RewardSponsor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Rewards';

    protected static ?string $navigationLabel = 'Sponsors';

    protected static ?string $modelLabel = 'sponsor';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('marketing') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('category')->options(RewardSponsor::CATEGORIES)->required()->default('other')->native(false),
            FileUpload::make('logo')->image()->disk('public')->directory('rewards/sponsors')->maxSize(1024),
            ColorPicker::make('brand_color'),
            TextInput::make('website_url')->url()->maxLength(255)->rule('starts_with:https://'),
            TextInput::make('contact_name')->maxLength(120),
            TextInput::make('contact_email')->email()->maxLength(190),
            Textarea::make('notes')->rows(3)->maxLength(2000)
                ->helperText('Internal notes. Never paste API keys or passwords here — credentials belong in the server environment.'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')->disk('public')->circular()->label(''),
                TextColumn::make('name')->weight('bold')->searchable(),
                TextColumn::make('category')->badge()->formatStateUsing(fn (string $state): string => RewardSponsor::CATEGORIES[$state] ?? $state),
                TextColumn::make('programs_count')->counts('programs')->label('Programs'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->after(fn (RewardSponsor $record) => AdminAction::log('reward_sponsor.deleted', ['sponsor_id' => $record->id, 'name' => $record->name])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRewardSponsors::route('/')];
    }
}
