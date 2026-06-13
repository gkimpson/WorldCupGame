<?php

namespace App\Filament\Resources\Players\Tables;

use App\Enums\PlayerPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('team.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->badge()
                    ->sortable(),
                TextColumn::make('shirt_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('team')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('position')
                    ->options(PlayerPosition::class),
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
