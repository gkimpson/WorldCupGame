<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('stat.total_points')
                    ->label('Points')
                    ->default(0)
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->boolean(),
                IconColumn::make('is_dummy')
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn ($record) => filled($record->email_verified_at)),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Admin'),
                TernaryFilter::make('is_dummy')
                    ->label('Dummy account'),
                Filter::make('verified')
                    ->label('Email verified')
                    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
                Filter::make('unverified')
                    ->label('Email unverified')
                    ->query(fn (Builder $query) => $query->whereNull('email_verified_at')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
