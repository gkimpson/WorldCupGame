<?php

namespace App\Filament\Resources\Teams\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('group')
                    ->badge()
                    ->sortable(),
                TextColumn::make('confederation')
                    ->searchable(),
                TextColumn::make('players_count')
                    ->label('Players')
                    ->counts('players')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('group')
                    ->options(array_combine(range('A', 'L'), range('A', 'L'))),
                SelectFilter::make('confederation')
                    ->options([
                        'UEFA' => 'UEFA',
                        'CONMEBOL' => 'CONMEBOL',
                        'CONCACAF' => 'CONCACAF',
                        'CAF' => 'CAF',
                        'AFC' => 'AFC',
                        'OFC' => 'OFC',
                    ]),
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
