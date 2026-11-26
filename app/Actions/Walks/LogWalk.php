<?php

namespace App\Actions\Walks;

use App\Enums\GroupRole;
use App\Enums\WalkStatus;
use App\Models\Ascent;
use App\Models\Group;
use App\Models\Guest;
use App\Models\User;
use App\Models\Walk;
use App\Models\WalkAttendee;
use App\Models\WalkDay;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records a walk that has already happened: one day or several, each with its
 * hills in order, and who did which hills (docs/SPEC.md §5). Everything is
 * checked again here, whatever the form allowed, because this is what writes
 * ascents into people's histories.
 */
class LogWalk
{
    use ChecksWalkDays;

    /**
     * @param  array{group_id?: ?int, title?: ?string, notes?: ?string, days: list<array<string, mixed>>, people?: list<int>, ticks?: array<int, array<int, list<int>>>, guests?: list<string>}  $data
     *
     * @throws AuthorizationException when the logger can't log into that group
     * @throws ValidationException when the walk doesn't make sense
     */
    public function handle(User $logger, array $data): Walk
    {
        $group = $this->group($logger, $data['group_id'] ?? null);
        $people = $this->people($logger, $group, $data['people'] ?? []);
        $days = $this->days($data['days'], $people, $data['ticks'] ?? []);
        $guests = $group === null ? [] : $this->guests($data['guests'] ?? []);

        return DB::transaction(function () use ($logger, $group, $people, $days, $guests, $data) {
            $walk = Walk::create([
                'group_id' => $group?->id,
                'title' => filled($data['title'] ?? null) ? Str::limit(trim($data['title']), 250, '') : null,
                'status' => WalkStatus::Completed,
                'organiser_id' => $logger->id,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);

            foreach ($days as $index => $day) {
                $walkDay = WalkDay::create([
                    'walk_id' => $walk->id,
                    'date' => $day['date'],
                    'day_number' => $index + 1,
                    'direction' => $day['direction'],
                    'duration_minutes' => $day['duration_minutes'],
                ]);

                foreach ($day['hills'] as $position => $hillId) {
                    $walkDay->hills()->attach($hillId, ['position' => $position + 1]);

                    foreach ($day['ticks'][$hillId] as $userId) {
                        Ascent::create(['user_id' => $userId, 'hill_id' => $hillId, 'walk_day_id' => $walkDay->id]);
                    }
                }
            }

            foreach ($people as $userId) {
                WalkAttendee::create(['walk_id' => $walk->id, 'user_id' => $userId, 'attended' => true]);
            }

            foreach ($guests as $name) {
                $guest = Guest::firstOrCreate(['group_id' => $group->id, 'name' => $name]);
                WalkAttendee::create(['walk_id' => $walk->id, 'guest_id' => $guest->id, 'attended' => true]);
            }

            return $walk;
        });
    }

    /**
     * Only an admin or member can log into a group. A follower or a visitor can't.
     */
    private function group(User $logger, ?int $groupId): ?Group
    {
        if ($groupId === null) {
            return null;
        }

        $group = Group::find($groupId);
        $role = $group?->roleOf($logger);

        if ($group === null || ! in_array($role, [GroupRole::Admin, GroupRole::Member], true)) {
            throw new AuthorizationException(__('You can only log walks for groups you are a member of.'));
        }

        return $group;
    }

    /**
     * Who did the walk. A solo walk is just the logger; a group walk is people who
     * are admins or members of that group.
     *
     * @param  list<int>  $requested
     * @return list<int>
     */
    private function people(User $logger, ?Group $group, array $requested): array
    {
        if ($group === null) {
            return [$logger->id];
        }

        $allowed = $group->users()
            ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Member->value])
            ->pluck('users.id')
            ->all();

        $people = array_values(array_unique(array_map('intval', $requested)));

        if ($people === [] || array_diff($people, $allowed) !== []) {
            throw ValidationException::withMessages([
                'people' => __('Pick who was on the walk from the people in the group.'),
            ]);
        }

        return $people;
    }
}
