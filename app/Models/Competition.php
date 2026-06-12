<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $provider_league_id
 * @property int|null $season
 */
#[Fillable(['name', 'slug', 'provider_league_id', 'season'])]
class Competition extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider_league_id' => 'integer',
            'season' => 'integer',
        ];
    }
}
