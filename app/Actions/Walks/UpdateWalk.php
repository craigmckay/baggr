<?php

namespace App\Actions\Walks;

use App\Enums\GroupRole;
use App\Models\Ascent;
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
 * Corrects a completed walk (docs/SPEC.md §5). The walk, its days and its ascents
 * are changed where they are rather than made again, so an ascent's note stays
 * with it. The group, or that the walk was solo, never changes.
 */
class UpdateWalk
{
    use ChecksWalkDays;

    /**
     * @param  array{title?: ?string, notes?: ?string, days: list<array<string, mixed>>, people?: list<int>, ticks?: array<int, array<int, list<int>>>, guests?: list<string>}  $data  a day that is already on the walk carries its "id"
     *
     * @throws AuthorizationException when the editor may not change this walk
     * @throws ValidationException when the walk doesn't make sense
     */
    public function handle(User $editor, Walk $walk, array $data): Walk
    {
        if (! $walk->canBeEditedBy($editor)) {
            throw new AuthorizationException(__('You can\'t edit this walk.'));
        }

        $people = $this->people($walk, $data['people'] ?? []);
        $days = $this->days($data['days'], $people, $data['ticks'] ?? [], $this->unclimbed($walk));
        $guests = $walk->group_id === null ? [] : $this->guests($data['guests'] ?? []);

        return DB::transaction(function () use ($walk, $people, $days, $guests, $data) {
            $walk->update([
                'title' => filled($data['title'] ?? null) ? Str::limit(trim($data['title']), 250, '') : null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            ]);

            $this->saveDays($walk, $days);
            $this->saveAttendees($walk, $people, $guests);

            return $walk;
        });
    }

    /**
     * People already on the walk stay choosable, even if they have since left the group.
     * Anyone new must be an admin or member of it. A solo walk's people don't change.
     *
     * @param  list<int>  $requested
     * @return list<int>
     */
    private function people(Walk $walk, array $requested): array
    {
        $there = $walk->peopleIds();

        if ($walk->group === null) {
            return $there;
        }

        $allowed = array_merge($there, $walk->group->users()
            ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Member->value])
            ->pluck('users.id')
            ->all());

        $people = array_values(array_unique(array_map('intval', $requested)));

        if ($people === [] || array_diff($people, $allowed) !== []) {
            throw ValidationException::withMessages([
                'people' => __('Pick who was on the walk from the people in the group.'),
            ]);
        }

        return $people;
    }

    /**
     * Hills nobody is recorded on, which happens when an account is deleted. They may stay so.
     *
     * @return list<int>
     */
    private function unclimbed(Walk $walk): array
    {
        $unclimbed = [];

        foreach ($walk->days()->with(['hills', 'ascents'])->get() as $day) {
            foreach ($day->hills as $hill) {
                if (! $day->ascents->contains('hill_id', $hill->id)) {
                    $unclimbed[] = $hill->id;
                }
            }
        }

        return $unclimbed;
    }

    /**
     * @param  list<array{id: ?int, date: string, direction: ?string, duration_minutes: ?int, hills: list<int>, ticks: array<int, list<int>>}>  $days
     */
    private function saveDays(Walk $walk, array $days): void
    {
        // Day numbers are unique within a walk, so move them all out of the way before renumbering.
        // Read the days afterwards, or a day keeping its number would look unchanged and not be saved.
        WalkDay::where('walk_id', $walk->id)->increment('day_number', 1000);

        $existing = $walk->days()->get()->keyBy('id');
        $old = Ascent::whereIn('walk_day_id', $existing->keys())->get();
        $wanted = [];
        $kept = [];

        foreach ($days as $index => $day) {
            // An id that isn't one of this walk's days, or is used twice, makes a new day.
            $walkDay = in_array($day['id'], $kept, true) ? null : $existing->get($day['id'] ?? 0);
            $walkDay ??= new WalkDay(['walk_id' => $walk->id]);

            $walkDay->forceFill([
                'date' => $day['date'],
                'day_number' => $index + 1,
                'direction' => $day['direction'],
                'duration_minutes' => $day['duration_minutes'],
            ])->save();

            $kept[] = $walkDay->id;

            $positions = [];
            foreach ($day['hills'] as $position => $hillId) {
                $positions[$hillId] = ['position' => $position + 1];

                foreach ($day['ticks'][$hillId] as $userId) {
                    $wanted[] = ['user_id' => $userId, 'hill_id' => $hillId, 'walk_day_id' => $walkDay->id];
                }
            }
            $walkDay->hills()->sync($positions);
        }

        $this->saveAscents($old->all(), $wanted);

        // Last, because deleting a day takes any ascent still on it.
        WalkDay::where('walk_id', $walk->id)->whereNotIn('id', $kept)->delete();
    }

    /**
     * An ascent that is still there is left alone. One whose hill moved to another
     * day moves with it, keeping its note. The rest are removed or added.
     *
     * @param  array<int, Ascent>  $old
     * @param  list<array{user_id: int, hill_id: int, walk_day_id: int}>  $wanted
     */
    private function saveAscents(array $old, array $wanted): void
    {
        foreach ($wanted as $i => $want) {
            foreach ($old as $key => $ascent) {
                if ($ascent->user_id === $want['user_id'] && $ascent->hill_id === $want['hill_id'] && $ascent->walk_day_id === $want['walk_day_id']) {
                    unset($old[$key], $wanted[$i]);
                    break;
                }
            }
        }

        $moves = [];
        foreach ($wanted as $i => $want) {
            foreach ($old as $key => $ascent) {
                if ($ascent->user_id === $want['user_id'] && $ascent->hill_id === $want['hill_id']) {
                    $moves[] = [$ascent, $want['walk_day_id']];
                    unset($old[$key], $wanted[$i]);
                    break;
                }
            }
        }

        foreach ($old as $ascent) {
            $ascent->delete();
        }

        foreach ($moves as [$ascent, $dayId]) {
            $ascent->update(['walk_day_id' => $dayId]);
        }

        foreach ($wanted as $want) {
            Ascent::create($want);
        }
    }

    /**
     * A "former member" (an attendee with neither a person nor a guest) is left as is.
     *
     * @param  list<int>  $people
     * @param  list<string>  $guests
     */
    private function saveAttendees(Walk $walk, array $people, array $guests): void
    {
        $guestIds = array_map(
            fn (string $name) => Guest::firstOrCreate(['group_id' => $walk->group_id, 'name' => $name])->id,
            $guests,
        );

        $walk->attendees()->whereNotNull('user_id')->whereNotIn('user_id', $people)->delete();
        $walk->attendees()->whereNotNull('guest_id')->whereNotIn('guest_id', $guestIds)->delete();

        $userIds = $walk->attendees()->whereNotNull('user_id')->pluck('user_id')->all();
        foreach (array_diff($people, $userIds) as $userId) {
            WalkAttendee::create(['walk_id' => $walk->id, 'user_id' => $userId, 'attended' => true]);
        }

        $already = $walk->attendees()->whereNotNull('guest_id')->pluck('guest_id')->all();
        foreach (array_diff($guestIds, $already) as $guestId) {
            WalkAttendee::create(['walk_id' => $walk->id, 'guest_id' => $guestId, 'attended' => true]);
        }
    }
}
