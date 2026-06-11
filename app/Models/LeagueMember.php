<?php

namespace App\Models;

use App\Enums\LeagueMemberRole;
use Database\Factories\LeagueMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $league_id
 * @property int $user_id
 * @property LeagueMemberRole $role
 * @property Carbon $joined_at
 */
#[Fillable(['league_id', 'user_id', 'role', 'joined_at'])]
class LeagueMember extends Model
{
    /** @use HasFactory<LeagueMemberFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => LeagueMemberRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
