<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalDocuments;

use App\Filament\Resources\LegalDocuments\Pages\EditLegalDocument;
use App\Filament\Resources\LegalDocuments\Pages\ListLegalDocuments;
use App\Models\LegalDocument;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Terms & Conditions and the Privacy Policy, as read by the app's legal screens.
 *
 * There is no create or delete action: the app links to exactly two fixed slugs
 * ('terms', 'privacy'), both seeded by migration. Letting an admin delete one
 * would 404 a screen that every user can reach.
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $recordTitleAttribute = 'title';

    /** Legal copy is platform-wide and binding — super-admins only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->disabled()
                ->helperText('The app fetches /api/legal/{slug}. Fixed — not editable.'),
            TextInput::make('title')
                ->required()
                ->helperText('Shown as the screen title in the app.'),
            Textarea::make('body')
                ->required()
                ->rows(24)
                ->columnSpanFull()
                ->helperText('Plain text. Blank lines separate paragraphs; a short line ending in ":" renders as a heading.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->badge(),
                TextColumn::make('body')->label('Length')->state(fn (LegalDocument $r): string => strlen($r->body) . ' chars'),
                TextColumn::make('updated_at')->label('Last published')->dateTime()->sortable()->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalDocuments::route('/'),
            'edit' => EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
