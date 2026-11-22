<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $colour
 * @property string $hill_grouping
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** The colour of a person's solo hills until they choose another (brand heather). */
    public const DEFAULT_COLOUR = '#C9B5DD';

    /**
     * The same defaults as the database, so a person just created has them too.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'colour' => self::DEFAULT_COLOUR,
        'hill_grouping' => 'mwis',
    ];

    /**
     * The colours a person can pick for their solo hills, by name.
     *
     * @return array<string, string>
     */
    public static function colours(): array
    {
        return ['Light heather' => self::DEFAULT_COLOUR] + Group::COLOURS;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The groups this person is in, with their role in each.
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Every hill this person has climbed, repeats included.
     *
     * @return HasMany<Ascent, $this>
     */
    public function ascents(): HasMany
    {
        return $this->hasMany(Ascent::class);
    }

    /**
     * Invitations by email that are still open for this person's address.
     *
     * @return Collection<int, GroupInvite>
     */
    public function openInvites(): Collection
    {
        return GroupInvite::with('group')
            ->where('email', Str::lower($this->email))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
