<?php

namespace App\Filament\Resources\Players\Schemas;

use App\Enums\PlayerPosition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('position')
                    ->options(PlayerPosition::class)
                    ->required(),
                TextInput::make('shirt_number')
                    ->numeric(),
                DatePicker::make('date_of_birth'),
            ]);
    }
}
