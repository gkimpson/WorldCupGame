<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Code')
                    ->maxLength(3)
                    ->nullable(),
                Select::make('group')
                    ->options(array_combine(range('A', 'L'), range('A', 'L')))
                    ->nullable(),
                Select::make('confederation')
                    ->options([
                        'UEFA' => 'UEFA',
                        'CONMEBOL' => 'CONMEBOL',
                        'CONCACAF' => 'CONCACAF',
                        'CAF' => 'CAF',
                        'AFC' => 'AFC',
                        'OFC' => 'OFC',
                    ])
                    ->nullable(),
                TextInput::make('flag_code')
                    ->label('Flag Code')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }
}
