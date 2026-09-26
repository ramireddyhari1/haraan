<?php

namespace App\Filament\Resources\WhatsAppQuickReplies;

use App\Filament\Resources\WhatsAppQuickReplies\Pages\CreateWhatsAppQuickReply;
use App\Filament\Resources\WhatsAppQuickReplies\Pages\EditWhatsAppQuickReply;
use App\Filament\Resources\WhatsAppQuickReplies\Pages\ListWhatsAppQuickReplies;
use App\Filament\Resources\WhatsAppQuickReplies\Schemas\WhatsAppQuickReplyForm;
use App\Filament\Resources\WhatsAppQuickReplies\Tables\WhatsAppQuickRepliesTable;
use App\Models\WhatsAppQuickReply;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * The canned replies venue staff tap in the partner app's WhatsApp Desk.
 *
 * A reply with no venue is the platform default every venue gets; a venue's own reply
 * with the same shortcut replaces it for that venue. Bodies use {{tokens}} the desk
 * fills from the venue's real details, and a reply whose token the venue hasn't set
 * (no address, no rules) is hidden rather than sent with a blank in it.
 */
class WhatsAppQuickReplyResource extends Resource
{
    protected static ?string $model = WhatsAppQuickReply::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'WhatsApp Desk replies';

    protected static ?string $modelLabel = 'quick reply';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 93;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Platform';
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsAppQuickReplyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppQuickRepliesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppQuickReplies::route('/'),
            'create' => CreateWhatsAppQuickReply::route('/create'),
            'edit' => EditWhatsAppQuickReply::route('/{record}/edit'),
        ];
    }
}
