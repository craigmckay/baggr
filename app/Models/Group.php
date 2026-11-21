<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Exceptions\GroupRuleException;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $colour
 * @property string|null $join_token
 * @property Carbon|null $join_token_expires_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** How many people can wait for approval at once, so a leaked link can't flood a group. */
    public const MAX_WAITING_VISITORS = 30;

    /** How long the reusable link and an emailed invitation last. */
    public const LINK_DAYS = 7;

    /**
     * The colours a group can have, by name. The first is Baggr's hill green.
     *
     * @var array<string, string>
     */
    public const COLOURS = [
        'Hill green' => '#1F4D3A',
        'Heather' => '#8F6BB0',
        'Loch blue' => '#2F6F9F',
        'Bracken' => '#B5651D',
        'Rowan' => '#C0392B',
        'Gorse' => '#C9A227',
        'Slate' => '#5D6B75',
        'Moss' => '#6E8B3D',
    ];

    protected $fillable = ['name', 'colour'];

    protected function casts(): array
    {
        return ['join_token_expires_at' => 'datetime'];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<Walk, $this>
     */
    public function walks(): HasMany
    {
        return $this->hasMany(Walk::class);
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * @return HasMany<GroupInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(GroupInvite::class);
    }

    public function roleOf(User $user): ?GroupRole
    {
        $role = $this->users()->whereKey($user->getKey())->first()?->pivot?->getAttribute('role');

        return is_string($role) ? GroupRole::from($role) : null;
    }

    public function isAdmin(User $user): bool
    {
        return $this->roleOf($user) === GroupRole::Admin;
    }

    public function countWithRole(GroupRole $role): int
    {
        return $this->users()->wherePivot('role', $role->value)->count();
    }

    /**
     * Add someone, or change their role if they are already in the group.
     */
    public function addMember(User $user, GroupRole $role): void
    {
        if ($this->roleOf($user) === null) {
            $this->users()->attach($user, ['role' => $role->value]);

            return;
        }

        $this->setRole($user, $role);
    }

    /**
     * @throws GroupRuleException when this would leave the group without an admin
     */
    public function setRole(User $user, GroupRole $role): void
    {
        $this->guardLastAdmin($user, $role);

        $this->users()->updateExistingPivot($user->getKey(), ['role' => $role->value]);
    }

    /**
     * @throws GroupRuleException when this would leave the group without an admin
     */
    public function removeMember(User $user): void
    {
        $this->guardLastAdmin($user, null);

        $this->users()->detach($user->getKey());
    }

    /**
     * A group always keeps at least one admin.
     */
    private function guardLastAdmin(User $user, ?GroupRole $newRole): void
    {
        if ($newRole === GroupRole::Admin) {
            return;
        }

        if ($this->roleOf($user) === GroupRole::Admin && $this->countWithRole(GroupRole::Admin) <= 1) {
            throw new GroupRuleException(__('A group needs at least one admin. Make someone else an admin first.'));
        }
    }

    public function hasRoomForVisitors(): bool
    {
        return $this->countWithRole(GroupRole::Visitor) < self::MAX_WAITING_VISITORS;
    }

    public function joinLinkIsLive(): bool
    {
        return $this->join_token !== null
            && $this->join_token_expires_at !== null
            && $this->join_token_expires_at->isFuture();
    }

    /**
     * Make a new reusable link, which replaces any earlier one.
     */
    public function makeJoinLink(): void
    {
        $this->forceFill([
            'join_token' => Str::random(40),
            'join_token_expires_at' => now()->addDays(self::LINK_DAYS),
        ])->save();
    }

    public function revokeJoinLink(): void
    {
        $this->forceFill(['join_token' => null, 'join_token_expires_at' => null])->save();
    }

    /**
     * The group a still-valid reusable link belongs to.
     */
    public static function findByLiveJoinToken(string $token): ?self
    {
        $group = self::where('join_token', $token)->first();

        return $group?->joinLinkIsLive() ? $group : null;
    }
}
