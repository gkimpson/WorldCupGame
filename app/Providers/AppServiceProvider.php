<?php

namespace App\Providers;

use App\Console\Commands\SyncResultsFromGemini;
use App\Console\Commands\SyncResultsFromOpenAi;
use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\ApiFootball\Contracts\FootballDataProviderInterface;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use App\Services\Results\GeminiResultsService;
use App\Services\Results\OpenAiResultsService;
use App\Services\Results\ResultsResponseParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FootballDataProviderInterface::class, ApiFootballClient::class);

        $this->app->singleton(ResultsResponseParser::class);

        $this->app->singleton(GeminiResultsService::class, fn () => new GeminiResultsService(
            apiKey: (string) config('services.gemini.key'),
            model: (string) config('services.gemini.model', 'gemini-2.5-flash'),
            parser: $this->app->make(ResultsResponseParser::class),
        ));

        $this->app->singleton(OpenAiResultsService::class, fn () => new OpenAiResultsService(
            apiKey: (string) config('services.openai.key'),
            model: (string) config('services.openai.model', 'gpt-4o-mini'),
            parser: $this->app->make(ResultsResponseParser::class),
        ));

        $this->app->when(SyncResultsFromGemini::class)
            ->needs(WorldCupResultsProviderInterface::class)
            ->give(GeminiResultsService::class);

        $this->app->when(SyncResultsFromOpenAi::class)
            ->needs(WorldCupResultsProviderInterface::class)
            ->give(OpenAiResultsService::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(ResultImported::class, RecalculateFixturePredictions::class);
        Event::listen(ResultImported::class, RecalculateUserStats::class);
    }

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
