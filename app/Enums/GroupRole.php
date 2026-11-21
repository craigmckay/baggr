<?php

namespace App\Enums;

/**
 * What a person can do in a group (docs/SPEC.md §2).
 */
enum GroupRole: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Follower = 'follower';
    case Visitor = 'visitor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Member => __('Member'),
            self::Follower => __('Follower'),
            self::Visitor => __('Visitor'),
        };
    }

    /**
     * The roles an admin can give someone. Visitor is only what the group's
     * shared link produces, so it is never on offer.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member, self::Follower];
    }

    public function isAssignable(): bool
    {
        return in_array($this, self::assignable(), true);
    }
}
