<?php

namespace App\Filament\Resources;

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Filament\Resources\FixtureResource\Pages;
use App\Models\Fixture;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FixtureResource extends Resource
{
    protected static ?string $model = Fixture::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Fixtures';

    protected static \UnitEnum|string|null $navigationGroup = 'Competition';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')
                ->options(FixtureStatus::class)
                ->required(),
            TextInput::make('home_team_placeholder')->maxLength(255),
            TextInput::make('away_team_placeholder')->maxLength(255),
            TextInput::make('home_score')->numeric()->minValue(0),
            TextInput::make('away_score')->numeric()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('match_number')->label('#')->sortable(),
                TextColumn::make('homeTeam.name')
                    ->label('Home')
                    ->default(fn (Fixture $record) => $record->home_team_placeholder ?? '—'),
                TextColumn::make('awayTeam.name')
                    ->label('Away')
                    ->default(fn (Fixture $record) => $record->away_team_placeholder ?? '—'),
                TextColumn::make('home_score')->label('H')->default('—'),
                TextColumn::make('away_score')->label('A')->default('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('scheduled_at')->dateTime()->sortable(),
            ])
            ->defaultSort('match_number')
            ->actions([
                Action::make('importResult')
                    ->label('Import Result')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        TextInput::make('home_score')
                            ->label('Home Score')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('away_score')
                            ->label('Away Score')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->action(function (Fixture $record, array $data): void {
                        $record->update([
                            'home_score' => $data['home_score'],
                            'away_score' => $data['away_score'],
                            'status' => FixtureStatus::Completed,
                        ]);

                        ResultImported::dispatch($record->fresh());
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixtures::route('/'),
            'create' => Pages\CreateFixture::route('/create'),
            'edit' => Pages\EditFixture::route('/{record}/edit'),
        ];
    }
}
