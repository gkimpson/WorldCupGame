<?php

namespace App\Filament\Resources\Leagues\RelationManagers;

use App\Enums\LeagueMemberRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeagueMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->options(LeagueMemberRole::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user_id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                TextColumn::make('joined_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([
                EditAction::make()->label('Change Role'),
                DeleteAction::make()->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Remove Selected'),
                ]),
            ]);
    }
}
