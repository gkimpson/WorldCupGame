<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\FixtureSyncService;
use Illuminate\Console\Command;

final class SyncFixtures extends Command
{
    protected $signature = 'world-cup:sync-fixtures
                            {--dry-run : Preview changes without persisting them}';

    protected $description = 'Sync World Cup 2026 fixture data from API-Football';

    public function handle(FixtureSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry-run mode: no changes will be written.');
        }

        $this->info('Fetching fixtures from API-Football...');

        try {
            $result = $service->syncFromProvider(dryRun: $dryRun);
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Synced', 'Skipped', 'Completed (events fired)'],
            [[$result['synced'], $result['skipped'], $result['completed']]],
        );

        return self::SUCCESS;
    }
}
