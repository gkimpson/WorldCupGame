<?php

namespace App\Models;

use Database\Factories\LeagueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $owner_user_id
 * @property string $name
 * @property string $join_code
 */
#[Fillable(['owner_user_id', 'name', 'join_code'])]
class League extends Model
{
    /** @use HasFactory<LeagueFactory> */
    use HasFactory;

    use HasUlids;

    public static function generateJoinCode(): string
    {
        do {
            $joinCode = Str::upper(Str::random(8));
        } while (self::where('join_code', $joinCode)->exists());

        return $joinCode;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<LeagueMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->exists();
    }
}
