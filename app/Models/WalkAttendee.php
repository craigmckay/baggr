<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person or guest on a walk. With neither set, the account has been deleted
 * and the walk shows a "former member".
 */
class WalkAttendee extends Model
{
    protected $fillable = ['walk_id', 'user_id', 'guest_id', 'rsvp', 'attended'];

    protected function casts(): array
    {
        return ['attended' => 'boolean'];
    }

    /**
     * @return BelongsTo<Walk, $this>
     */
    public function walk(): BelongsTo
    {
        return $this->belongsTo(Walk::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function displayName(): string
    {
        // A deleted account leaves both ids empty, so the walk still reads sensibly.
        if ($this->user_id !== null) {
            return $this->user->name;
        }

        return $this->guest_id !== null ? $this->guest->name : __('Former member');
    }
}
