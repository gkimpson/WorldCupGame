<?php

namespace App\Models;

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property string|null $home_team_placeholder
 * @property string|null $away_team_placeholder
 * @property FixtureStage $stage
 * @property string|null $group
 * @property int|null $match_number
 * @property string|null $venue
 * @property string|null $city
 * @property Carbon|null $scheduled_at
 * @property FixtureStatus $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property int|null $home_score_aet
 * @property int|null $away_score_aet
 * @property int|null $home_score_pens
 * @property int|null $away_score_pens
 */
#[Fillable([
    'home_team_id', 'away_team_id',
    'home_team_placeholder', 'away_team_placeholder',
    'stage', 'group', 'match_number',
    'venue', 'city', 'scheduled_at', 'status',
    'home_score', 'away_score',
    'home_score_aet', 'away_score_aet',
    'home_score_pens', 'away_score_pens',
])]
class Fixture extends Model
{
    public const TOTAL_WORLD_CUP_MATCHES = 104;

    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => FixtureStage::class,
            'status' => FixtureStatus::class,
            'scheduled_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_score_aet' => 'integer',
            'away_score_aet' => 'integer',
            'home_score_pens' => 'integer',
            'away_score_pens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @return HasMany<Prediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }
}
