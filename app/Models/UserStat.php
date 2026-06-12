<?php

namespace App\Models;

use Database\Factories\UserStatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property int $total_points
 * @property int $predictions_made
 * @property int $correct_outcomes
 * @property int $exact_scores
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'total_points', 'predictions_made', 'correct_outcomes', 'exact_scores'])]
class UserStat extends Model
{
    /** @use HasFactory<UserStatFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'predictions_made' => 'integer',
            'correct_outcomes' => 'integer',
            'exact_scores' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<UserStat> $query */
    #[Scope]
    protected function forRealUsers(Builder $query): void
    {
        $query->whereHas('user', fn (Builder $query) => $query->where('is_dummy', false));
    }
}
