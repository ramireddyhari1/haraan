<?php

namespace App\Filament\Resources\EmailSenders;

use App\Filament\Resources\EmailSenders\Pages\CreateEmailSender;
use App\Filament\Resources\EmailSenders\Pages\EditEmailSender;
use App\Filament\Resources\EmailSenders\Pages\ListEmailSenders;
use App\Filament\Resources\EmailSenders\Schemas\EmailSenderForm;
use App\Filament\Resources\EmailSenders\Tables\EmailSendersTable;
use App\Models\EmailSender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EmailSenderResource extends Resource
{
    protected static ?string $model = EmailSender::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Senders';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 90;

    // System-level messaging infrastructure — super-admins only (same gate as People/System).
    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function form(Schema $schema): Schema
    {
        return EmailSenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailSendersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailSenders::route('/'),
            'create' => CreateEmailSender::route('/create'),
            'edit' => EditEmailSender::route('/{record}/edit'),
        ];
    }
}
