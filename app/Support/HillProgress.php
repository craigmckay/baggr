<?php

namespace App\Support;

use App\Enums\HillGrouping;
use App\Models\Ascent;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One person's progress through one hill list (spec §6.1 and §6.2).
 *
 * A hill counts as done once the person has at least one ascent of it, and the
 * first ascent decides its colour: the person's own colour for a solo walk, or
 * the group's colour for a walk with a group. The scope narrows which ascents
 * are looked at: "all", "solo" (walks with no group) or a group's id.
 */
class HillProgress
{
    public const ALL = 'all';

    public const SOLO = 'solo';

    private string $list;

    private string $scope;

    /** @var Collection<int, Hill>|null */
    private ?Collection $hills = null;

    /** @var array<int, string>|null Hill id => colour of its first ascent, on this list */
    private ?array $done = null;

    /** @var array<int, string>|null The same for every hill, whatever the list */
    private ?array $everything = null;

    public function __construct(private readonly User $user, string $list, string $scope = self::ALL)
    {
        $this->list = array_key_exists($list, config('dobih.lists')) ? $list : (string) array_key_first(config('dobih.lists'));

        // A group only counts if the person is in it.
        $this->scope = match (true) {
            $scope === self::SOLO => self::SOLO,
            ctype_digit($scope) && $user->groups()->whereKey((int) $scope)->exists() => $scope,
            default => self::ALL,
        };
    }

    public function list(): string
    {
        return $this->list;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Every hill on the list, tallest first.
     *
     * @return Collection<int, Hill>
     */
    public function hills(): Collection
    {
        return $this->hills ??= Hill::query()
            ->select(['id', 'name', 'slug', 'height_m', 'region', 'mwis_forecast_url', 'smc_section', 'smc_section_description', 'rhb_section', 'rhb_section_description'])
            ->whereHas('classes', fn ($classes) => $classes->where('hill_classes.code', $this->list))
            ->orderByDesc('height_m')
            ->orderBy('name')
            ->get();
    }

    public function total(): int
    {
        return $this->hills()->count();
    }

    public function doneCount(): int
    {
        return $this->hills()->filter(fn (Hill $hill) => isset($this->done()[$hill->id]))->count();
    }

    public function percentage(): int
    {
        return $this->total() === 0 ? 0 : (int) floor($this->doneCount() / $this->total() * 100);
    }

    /**
     * The hills on the list, grouped into sections and in section order. Each
     * hill carries its colour if done, or null if it is still to do.
     *
     * @return list<array{key: string, label: string, done: int, total: int, hills: list<array{hill: Hill, colour: string|null}>}>
     */
    public function sections(HillGrouping $grouping): array
    {
        $sections = [];

        foreach ($this->hills() as $hill) {
            $section = $grouping->sectionFor($hill);

            $sections[$section['key']] ??= $section + ['done' => 0, 'total' => 0, 'hills' => []];
            $sections[$section['key']]['total']++;

            $colour = $this->done()[$hill->id] ?? null;

            if ($colour !== null) {
                $sections[$section['key']]['done']++;
            }

            $sections[$section['key']]['hills'][] = ['hill' => $hill, 'colour' => $colour];
        }

        uasort($sections, fn (array $a, array $b) => strnatcasecmp($a['sort'], $b['sort']));

        return array_values(array_map(
            fn (array $section) => [
                'key' => $section['key'],
                'label' => $section['label'],
                'done' => $section['done'],
                'total' => $section['total'],
                'hills' => $section['hills'],
            ],
            $sections,
        ));
    }

    /**
     * How far through every list the person is, whatever list is selected.
     *
     * @return array<string, array{name: string, done: int, total: int}>
     */
    public function summary(): array
    {
        $doneIds = array_keys($this->allDone());

        $counts = fn (array $ids) => DB::table('class_hill')
            ->join('hill_classes', 'hill_classes.id', '=', 'class_hill.hill_class_id')
            ->when($ids !== [], fn ($query) => $query->whereIn('class_hill.hill_id', $ids))
            ->selectRaw('hill_classes.code as code, count(*) as aggregate')
            ->groupBy('hill_classes.code')
            ->pluck('aggregate', 'code');

        $totals = $counts([]);
        $dones = $doneIds === [] ? collect() : $counts($doneIds);

        $summary = [];

        foreach (config('dobih.lists') as $code => $name) {
            $summary[$code] = [
                'name' => $name,
                'done' => (int) ($dones[$code] ?? 0),
                'total' => (int) ($totals[$code] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    private function done(): array
    {
        $listIds = $this->hills()->pluck('id')->flip();

        return $this->done ??= array_intersect_key($this->allDone(), $listIds->all());
    }

    /**
     * @return array<int, string>
     */
    private function allDone(): array
    {
        if ($this->everything !== null) {
            return $this->everything;
        }

        $ascents = Ascent::query()
            ->join('walk_days', 'walk_days.id', '=', 'ascents.walk_day_id')
            ->join('walks', 'walks.id', '=', 'walk_days.walk_id')
            ->where('ascents.user_id', $this->user->id)
            ->when($this->scope === self::SOLO, fn ($query) => $query->whereNull('walks.group_id'))
            ->when(ctype_digit($this->scope), fn ($query) => $query->where('walks.group_id', (int) $this->scope))
            ->orderBy('walk_days.date')
            ->orderBy('ascents.id')
            ->get(['ascents.hill_id', 'walks.group_id']);

        $groupColours = Group::query()
            ->whereIn('id', $ascents->pluck('group_id')->filter()->unique())
            ->pluck('colour', 'id');

        $done = [];

        foreach ($ascents as $ascent) {
            $done[(int) $ascent->getAttribute('hill_id')] ??= $ascent->getAttribute('group_id') !== null
                ? ($groupColours[$ascent->getAttribute('group_id')] ?? $this->user->colour)
                : $this->user->colour;
        }

        return $this->everything = $done;
    }
}
