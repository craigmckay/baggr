<?php

namespace App\Models;

use App\Enums\WalkDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $walk_id
 * @property Carbon|null $date
 * @property int $day_number
 * @property WalkDirection|null $direction
 * @property int|null $duration_minutes
 */
class WalkDay extends Model
{
    protected $fillable = ['walk_id', 'date', 'day_number', 'direction', 'duration_minutes'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'direction' => WalkDirection::class,
        ];
    }

    /**
     * @return BelongsTo<Walk, $this>
     */
    public function walk(): BelongsTo
    {
        return $this->belongsTo(Walk::class);
    }

    /**
     * The day's hills, in the order they were climbed.
     *
     * @return BelongsToMany<Hill, $this>
     */
    public function hills(): BelongsToMany
    {
        return $this->belongsToMany(Hill::class, 'walk_day_hill')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<Ascent, $this>
     */
    public function ascents(): HasMany
    {
        return $this->hasMany(Ascent::class);
    }
}
