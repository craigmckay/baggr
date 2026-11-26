<?php

namespace App\Actions\Walks;

use App\Enums\WalkDirection;
use App\Models\Hill;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The rules a walk's days and guests must meet, whether it is being logged or edited.
 */
trait ChecksWalkDays
{
    /**
     * Checks each day and works out who did each hill.
     *
     * @param  list<array<string, mixed>>  $days
     * @param  list<int>  $people
     * @param  array<int, array<int, list<int>>>  $ticks  by day index, then hill id
     * @param  list<int>  $mayBeUnclimbed  hills already on the walk with nobody against them
     * @return list<array{id: ?int, date: string, direction: ?string, duration_minutes: ?int, hills: list<int>, ticks: array<int, list<int>>}>
     */
    private function days(array $days, array $people, array $ticks, array $mayBeUnclimbed = []): array
    {
        if ($days === []) {
            throw ValidationException::withMessages(['days' => __('Add at least one day.')]);
        }

        $checked = [];

        foreach ($days as $index => $day) {
            $hills = array_values(array_map('intval', (array) ($day['hills'] ?? [])));

            if ($hills === []) {
                throw ValidationException::withMessages(['days' => __('Every day needs at least one hill.')]);
            }

            if (count($hills) !== count(array_unique($hills)) || Hill::whereIn('id', $hills)->count() !== count($hills)) {
                throw ValidationException::withMessages(['days' => __('A hill can only be on a day once.')]);
            }

            if (! filled($day['date'] ?? null) || strtotime((string) $day['date']) === false || (string) $day['date'] > now()->toDateString()) {
                throw ValidationException::withMessages(['days' => __('Each day needs a date that is not in the future.')]);
            }

            $direction = $day['direction'] ?? null;
            if (filled($direction) && WalkDirection::tryFrom((string) $direction) === null) {
                throw ValidationException::withMessages(['days' => __('That direction is not one of the choices.')]);
            }

            $dayTicks = [];
            foreach ($hills as $hillId) {
                $who = array_key_exists($hillId, $ticks[$index] ?? [])
                    ? array_values(array_unique(array_map('intval', $ticks[$index][$hillId])))
                    : $people;

                if (($who === [] && ! in_array($hillId, $mayBeUnclimbed, true)) || array_diff($who, $people) !== []) {
                    throw ValidationException::withMessages([
                        'days' => __('Every hill needs someone who climbed it, from the people on the walk.'),
                    ]);
                }

                $dayTicks[$hillId] = $who;
            }

            $checked[] = [
                'id' => filled($day['id'] ?? null) ? (int) $day['id'] : null,
                'date' => (string) $day['date'],
                'direction' => filled($direction) ? (string) $direction : null,
                'duration_minutes' => filled($day['duration_minutes'] ?? null) ? max(1, (int) $day['duration_minutes']) : null,
                'hills' => $hills,
                'ticks' => $dayTicks,
            ];
        }

        return $checked;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function guests(array $names): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($name) => Str::limit(trim((string) $name), 100, ''), $names),
            fn ($name) => $name !== '',
        )));
    }
}
