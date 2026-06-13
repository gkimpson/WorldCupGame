<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->hint(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep current password' : null)
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->dehydrated(fn (?string $state) => filled($state)),
                Toggle::make('is_dummy'),
                Toggle::make('is_admin'),
            ]);
    }
}
