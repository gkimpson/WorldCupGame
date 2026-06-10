<?php

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Filament\Resources\FixtureResource;
use App\Models\Fixture;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    actingAs($admin);
});

it('renders the fixture list page', function () {
    $this->get(FixtureResource::getUrl('index'))->assertOk();
});

it('fires ResultImported with the updated fixture after importing a result', function () {
    Event::fake([ResultImported::class]);

    $fixture = Fixture::factory()->create([
        'home_score' => null,
        'away_score' => null,
        'status' => FixtureStatus::Scheduled,
    ]);

    Livewire::test(FixtureResource\Pages\ListFixtures::class)
        ->callTableAction('importResult', $fixture, data: [
            'home_score' => 2,
            'away_score' => 1,
        ]);

    Event::assertDispatched(ResultImported::class, function (ResultImported $event) use ($fixture) {
        return $event->fixture->id === $fixture->id
            && $event->fixture->home_score === 2
            && $event->fixture->away_score === 1
            && $event->fixture->status->value === 'completed';
    });
});

it('updates the fixture scores and status in the database', function () {
    Event::fake([ResultImported::class]);

    $fixture = Fixture::factory()->create([
        'home_score' => null,
        'away_score' => null,
        'status' => FixtureStatus::Scheduled,
    ]);

    Livewire::test(FixtureResource\Pages\ListFixtures::class)
        ->callTableAction('importResult', $fixture, data: [
            'home_score' => 0,
            'away_score' => 0,
        ]);

    $fixture->refresh();
    expect($fixture->home_score)->toBe(0)
        ->and($fixture->away_score)->toBe(0)
        ->and($fixture->status)->toBe(FixtureStatus::Completed);
});
