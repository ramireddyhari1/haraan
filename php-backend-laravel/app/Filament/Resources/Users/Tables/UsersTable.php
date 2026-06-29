<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('avatar')
                    ->searchable(),
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('partner_type')
                    ->searchable(),
                TextColumn::make('event_host_id')
                    ->searchable(),
                TextColumn::make('player_id')
                    ->searchable(),
                TextColumn::make('player_role')
                    ->searchable(),
                TextColumn::make('playing_style')
                    ->searchable(),
                IconColumn::make('is_guest')
                    ->boolean(),
                TextColumn::make('district')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('batting_style')
                    ->searchable(),
                TextColumn::make('bowling_style')
                    ->searchable(),
                TextColumn::make('career_runs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('career_balls')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('career_matches')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('career_wickets')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('career_runs_conceded')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('career_overs_bowled')
                    ->searchable(),
                TextColumn::make('rank_district')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rank_state')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rank_country')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ranked_xp')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('casual_xp')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('trust_score')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_organizer')
                    ->boolean(),
                TextColumn::make('gender')
                    ->searchable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_place')
                    ->searchable(),
                TextColumn::make('height')
                    ->searchable(),
                TextColumn::make('nationality')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
