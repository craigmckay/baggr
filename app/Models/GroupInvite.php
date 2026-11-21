<?php

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An admin's emailed invitation: a single-use link for one address, with the
 * role the admin chose.
 *
 * @property int $id
 * @property int $group_id
 * @property string $email
 * @property GroupRole $role
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class GroupInvite extends Model
{
    protected $fillable = ['group_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'role' => GroupRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Make an invitation for an address, replacing any still-open one for the same group.
     */
    public static function issue(Group $group, string $email, GroupRole $role, User $by): self
    {
        $email = Str::lower(trim($email));

        self::where('group_id', $group->id)->where('email', $email)->whereNull('accepted_at')->delete();

        return self::create([
            'group_id' => $group->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(40),
            'invited_by' => $by->id,
            'expires_at' => now()->addDays(Group::LINK_DAYS),
        ]);
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function findOpen(string $token): ?self
    {
        $invite = self::where('token', $token)->first();

        return $invite?->isOpen() ? $invite : null;
    }
}
