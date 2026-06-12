<?php

namespace App\Providers;

use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use App\Services\DummyAwareEloquentUserProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('dummy-aware-eloquent', fn ($app, array $config): DummyAwareEloquentUserProvider => new DummyAwareEloquentUserProvider(
            $app['hash'],
            $config['model'],
        ));

        $this->configureDefaults();

        Event::listen(ResultImported::class, RecalculateFixturePredictions::class);
        Event::listen(ResultImported::class, RecalculateUserStats::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
