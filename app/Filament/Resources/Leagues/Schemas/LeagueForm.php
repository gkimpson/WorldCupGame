<?php

namespace App\Filament\Resources\Leagues\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeagueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('owner_user_id')
                    ->label('Owner')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('join_code')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
