<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class SyncResults extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'AI Sync Results';

    protected static \UnitEnum|string|null $navigationGroup = 'Competition';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.sync-results';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('AI SYNC RESULTS')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Match Results')
                ->modalDescription('This will run the AI sync pipeline to pull the latest match results. Continue?')
                ->action(function (): void {
                    try {
                        Artisan::call('world-cup:sync-results');

                        Notification::make()
                            ->title('Sync complete')
                            ->body('Match results have been synced successfully.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
