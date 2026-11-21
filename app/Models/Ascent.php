<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person on one hill on one walk day. Repeats are allowed; the first one is
 * what counts towards a total.
 */
class Ascent extends Model
{
    protected $fillable = ['user_id', 'hill_id', 'walk_day_id', 'notes'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Hill, $this>
     */
    public function hill(): BelongsTo
    {
        return $this->belongsTo(Hill::class);
    }

    /**
     * @return BelongsTo<WalkDay, $this>
     */
    public function walkDay(): BelongsTo
    {
        return $this->belongsTo(WalkDay::class);
    }
}
