<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\OrganizationUnit;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Manage the org memberships stored in user_organization_map (pivot:
 * designation, is_primary). This is how an admin assigns a manager to a District
 * or Venue so panel scoping kicks in. The home org_id column is set separately
 * on the user form; this pivot supports multi-org managers.
 */
class OrganizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizations';

    protected static ?string $title = 'Organization memberships';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('designation')
                ->maxLength(255)
                ->helperText('e.g. City Manager, Venue Owner'),
            Toggle::make('is_primary')
                ->helperText('Mark this as the member\'s primary unit.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('pivot.designation')->label('Designation'),
                IconColumn::make('pivot.is_primary')->label('Primary')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $this->scopeUnits($query))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('designation')->maxLength(255),
                        Toggle::make('is_primary'),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    /** Limit attachable units to the acting admin's own scope. */
    private function scopeUnits($query)
    {
        $allowed = auth()->user()?->scopedOrganizationIds();
        if ($allowed !== null) {
            $query->whereIn(OrganizationUnit::query()->getModel()->getTable().'.id', $allowed);
        }

        return $query;
    }
}
