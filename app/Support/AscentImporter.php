<?php

namespace App\Support;

use App\Actions\Walks\LogWalk;
use App\Enums\GroupRole;
use App\Models\Ascent;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use App\Models\Walk;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one-off load of the group's past ascents from munrotab_v7.1.xlsx (spec §5).
 *
 * It plans the walks from the workbook's rows, then logs each through the same
 * action as logging by hand, so the result is ordinary completed walks. Every
 * walk it makes is marked, so a second run skips what is already there and the
 * whole load can be taken back.
 *
 * @phpstan-type PlannedWalk array{label: string, title: string, group: ?Group, organiser: User, people: list<int>, days: list<array{date: string, hills: list<int>, ticks: array<int, list<int>>}>, notes: list<array{user: int, hill: int, date: string, text: string}>}
 */
class AscentImporter
{
    public const MARKER = 'Imported from munrotab v7.1';

    public function __construct(private LogWalk $logWalk) {}

    /**
     * Work out the walks without touching the database's walks.
     *
     * @param  list<array{running_no: int, climber: int, date: string, count: int, group: string}>  $rows  rows of the Climbed sheet, date as Y-m-d
     * @param  array<int, int>  $dobih  running number => DoBIH number (the Munros sheet)
     * @param  array<int, User>  $climbers  ClimberID => account
     * @param  array<string, Group>  $groups  group code => group; a code not listed is a solo walk
     * @param  list<array{group: string, date: string, user: User}>  $extras  people added to a group walk by hand
     * @return list<PlannedWalk>
     */
    public function plan(array $rows, array $dobih, array $climbers, array $groups, array $extras = []): array
    {
        $hills = Hill::whereIn('dobih_number', array_values($dobih))->get(['id', 'name', 'dobih_number']);
        $hillIds = $hills->pluck('id', 'dobih_number')->all();
        $names = $hills->pluck('name', 'id')->all();

        // Walk key => date => hill id => ['running' => n, 'who' => [user ids]]
        $byWalk = [];
        $notes = [];

        foreach ($rows as $row) {
            $user = $climbers[$row['climber']] ?? throw new InvalidArgumentException("Climber {$row['climber']} has no account given.");
            $hillId = $hillIds[$dobih[$row['running_no']] ?? 0] ?? throw new InvalidArgumentException("Running number {$row['running_no']} matches no hill.");
            $key = isset($groups[$row['group']]) ? "group:{$row['group']}" : "solo:{$row['group']}:{$user->id}";

            $byWalk[$key][$row['date']][$hillId]['running'] = $row['running_no'];
            $byWalk[$key][$row['date']][$hillId]['who'][] = $user->id;

            if ($row['count'] > 1) {
                $notes[$key][] = ['user' => $user->id, 'hill' => $hillId, 'date' => $row['date'],
                    'text' => "Climbed {$row['count']} times; only this date recorded"];
            }
        }

        foreach ($extras as $extra) {
            $key = "group:{$extra['group']}";

            if (! isset($byWalk[$key][$extra['date']])) {
                throw new InvalidArgumentException("There is no {$extra['group']} walk on {$extra['date']} to add {$extra['user']->name} to.");
            }

            foreach ($byWalk[$key][$extra['date']] as $hillId => $_) {
                $byWalk[$key][$extra['date']][$hillId]['who'][] = $extra['user']->id;
            }
        }

        $walks = [];

        foreach ($byWalk as $key => $dates) {
            foreach ($this->consecutive(array_keys($dates)) as $dayDates) {
                $days = [];
                $people = [];

                foreach ($dayDates as $date) {
                    $hills = $dates[$date];
                    uasort($hills, fn (array $a, array $b) => $a['running'] <=> $b['running']);

                    $ticks = [];
                    foreach ($hills as $hillId => $hill) {
                        $ticks[$hillId] = array_values(array_unique($hill['who']));
                        $people = [...$people, ...$ticks[$hillId]];
                    }

                    $days[] = ['date' => $date, 'hills' => array_keys($hills), 'ticks' => $ticks];
                }

                $people = array_values(array_unique($people));
                $group = str_starts_with($key, 'group:') ? $groups[substr($key, 6)] : null;

                $walks[] = [
                    'label' => $key.' '.$dayDates[0],
                    'title' => $this->title(array_merge(...array_column($days, 'hills')), $names),
                    'group' => $group,
                    'organiser' => $this->organiser($group, $people, $climbers, $key),
                    'people' => $people,
                    'days' => $days,
                    'notes' => array_values(array_filter($notes[$key] ?? [], fn (array $note) => in_array($note['date'], $dayDates, true))),
                ];
            }
        }

        usort($walks, fn (array $a, array $b) => [$a['days'][0]['date'], $a['label']] <=> [$b['days'][0]['date'], $b['label']]);

        return $walks;
    }

    /**
     * Log the planned walks. Anything already imported is skipped. Everything
     * happens in one transaction, so a problem part-way leaves nothing behind.
     *
     * @param  list<PlannedWalk>  $walks  from plan()
     * @return array{walks: int, skipped: int}
     */
    public function run(array $walks): array
    {
        $made = 0;
        $skipped = 0;

        DB::transaction(function () use ($walks, &$made, &$skipped) {
            foreach ($walks as $walk) {
                if ($this->alreadyImported($walk)) {
                    $skipped++;

                    continue;
                }

                $ticks = [];
                foreach ($walk['days'] as $index => $day) {
                    $ticks[$index] = $day['ticks'];
                }

                $logged = $this->logWalk->handle($walk['organiser'], [
                    'group_id' => $walk['group']?->id,
                    'title' => $walk['title'],
                    'notes' => self::MARKER,
                    'people' => $walk['people'],
                    'days' => array_map(fn (array $day) => ['date' => $day['date'], 'hills' => $day['hills']], $walk['days']),
                    'ticks' => $ticks,
                ]);

                foreach ($walk['notes'] as $note) {
                    Ascent::where('user_id', $note['user'])
                        ->where('hill_id', $note['hill'])
                        ->whereHas('walkDay', fn ($day) => $day->where('walk_id', $logged->id)->whereDate('date', $note['date']))
                        ->update(['notes' => $note['text']]);
                }

                $made++;
            }
        });

        return ['walks' => $made, 'skipped' => $skipped];
    }

    /**
     * Take back everything this importer made, and nothing else.
     */
    public function rollback(): int
    {
        $walks = Walk::where('notes', self::MARKER)->get();
        $walks->each->delete();

        return $walks->count();
    }

    /**
     * How many ascents, hills and walks each person would get.
     *
     * @param  list<PlannedWalk>  $walks  from plan()
     * @return array<int, array{ascents: int, hills: int, walks: int}> by user id
     */
    public function totals(array $walks): array
    {
        $totals = [];
        $hills = [];

        foreach ($walks as $walk) {
            foreach ($walk['people'] as $userId) {
                $totals[$userId] ??= ['ascents' => 0, 'hills' => 0, 'walks' => 0];
                $totals[$userId]['walks']++;
            }

            foreach ($walk['days'] as $day) {
                foreach ($day['ticks'] as $hillId => $who) {
                    foreach ($who as $userId) {
                        $totals[$userId] ??= ['ascents' => 0, 'hills' => 0, 'walks' => 0];
                        $totals[$userId]['ascents']++;
                        $hills[$userId][$hillId] = true;
                    }
                }
            }
        }

        foreach ($totals as $userId => $_) {
            $totals[$userId]['hills'] = count($hills[$userId] ?? []);
        }

        return $totals;
    }

    /**
     * @param  PlannedWalk  $walk
     */
    private function alreadyImported(array $walk): bool
    {
        return Walk::where('notes', self::MARKER)
            ->where('organiser_id', $walk['organiser']->id)
            ->where('group_id', $walk['group']?->id)
            ->whereHas('days', fn ($days) => $days->whereDate('date', $walk['days'][0]['date']))
            ->exists();
    }

    /**
     * "Hill X, Hill Y and Hill Z": the walk's hills in the order it did them.
     *
     * @param  list<int>  $hillIds
     * @param  array<int, string>  $names  hill id => name
     */
    private function title(array $hillIds, array $names): string
    {
        $list = array_map(fn (int $id) => $names[$id], $hillIds);
        $last = array_pop($list);

        return $list === [] ? $last : implode(', ', $list).' and '.$last;
    }

    /**
     * Dates in a row one day apart are one walk; any gap starts another.
     *
     * @param  list<string>  $dates
     * @return list<list<string>>
     */
    private function consecutive(array $dates): array
    {
        sort($dates);
        $trips = [];

        $current = [];

        foreach ($dates as $date) {
            if ($current !== [] && CarbonImmutable::parse(end($current))->addDay()->toDateString() !== $date) {
                $trips[] = $current;
                $current = [];
            }

            $current[] = $date;
        }

        if ($current !== []) {
            $trips[] = $current;
        }

        return $trips;
    }

    /**
     * A solo walk belongs to its walker. A group walk is logged by an admin of
     * the group who was on it, or failing that a member who was.
     *
     * @param  list<int>  $people
     * @param  array<int, User>  $climbers
     */
    private function organiser(?Group $group, array $people, array $climbers, string $key): User
    {
        if ($group === null) {
            return collect($climbers)->firstWhere('id', $people[0]) ?? User::findOrFail($people[0]);
        }

        // An admin who was on the walk, else any admin or member who was: both can log into the group.
        $logger = $group->users()
            ->wherePivotIn('role', [GroupRole::Admin->value, GroupRole::Member->value])
            ->whereIn('users.id', $people)
            ->orderByRaw("case when group_user.role = 'admin' then 0 else 1 end")
            ->orderBy('users.id')
            ->first();

        return $logger ?? throw new InvalidArgumentException("Nobody who can log into {$group->name} was on a {$key} walk.");
    }
}
