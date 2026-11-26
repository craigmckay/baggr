<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Enums\WalkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $group_id
 * @property string|null $title
 * @property WalkStatus $status
 * @property int|null $organiser_id
 * @property string|null $notes
 */
class Walk extends Model
{
    protected $fillable = ['group_id', 'title', 'status', 'organiser_id', 'notes'];

    protected function casts(): array
    {
        return ['status' => WalkStatus::class];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_id');
    }

    /**
     * @return HasMany<WalkDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(WalkDay::class)->orderBy('day_number');
    }

    /**
     * @return HasMany<WalkAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(WalkAttendee::class);
    }

    /**
     * What the walk is called in a list: its title, or else its first two hills.
     */
    public function displayName(): string
    {
        return $this->title ?: $this->days->flatMap->hills->pluck('name')->take(2)->implode(', ');
    }

    /**
     * Anyone who was on the walk, its organiser, or anyone let into its group. A visitor
     * waiting for approval is not let in.
     */
    public function canBeSeenBy(User $user): bool
    {
        if ($this->organiser_id === $user->id) {
            return true;
        }

        if ($this->attendees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($this->group === null) {
            return false;
        }

        $role = $this->group->roleOf($user);

        return $role !== null && $role !== GroupRole::Visitor;
    }

    /**
     * Everyone with an account who is recorded on the walk.
     *
     * @return list<int>
     */
    public function peopleIds(): array
    {
        $ids = $this->attendees()->whereNotNull('user_id')->pluck('user_id')
            ->merge(Ascent::whereIn('walk_day_id', $this->days()->pluck('id'))->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return array_values($ids);
    }

    /**
     * A completed walk can be corrected by anyone who was on it, whoever logged it and
     * its group's admins. On a group walk they must still be an admin or member.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($this->status !== WalkStatus::Completed) {
            return false;
        }

        $wasThere = $this->organiser_id === $user->id || $this->attendees()->where('user_id', $user->id)->exists();

        if ($this->group === null) {
            return $wasThere;
        }

        $role = $this->group->roleOf($user);

        return $role === GroupRole::Admin || ($role === GroupRole::Member && $wasThere);
    }

    /**
     * Whoever logged it, or an admin of its group.
     */
    public function canBeDeletedBy(User $user): bool
    {
        if ($this->organiser_id === $user->id) {
            return true;
        }

        return $this->group !== null && $this->group->isAdmin($user);
    }
}
