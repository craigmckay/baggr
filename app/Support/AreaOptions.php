<?php

namespace App\Support;

use App\Models\Area;
use Illuminate\Support\Facades\Cache;

/**
 * The DoBIH areas a person can pick to narrow a hill list (spec §3), grouped by
 * type and with the number of hills in each. Areas with no hills are left out.
 */
class AreaOptions
{
    /**
     * @return array<string, list<array{code: string, name: string, count: int}>> Type label => areas
     */
    public static function grouped(): array
    {
        return Cache::remember('area-options', now()->addHour(), function () {
            $areas = Area::query()
                ->join('area_hill', 'area_hill.area_id', '=', 'areas.id')
                ->groupBy('areas.id', 'areas.code', 'areas.name', 'areas.type')
                ->orderBy('areas.name')
                ->get(['areas.code', 'areas.name', 'areas.type'])
                ->groupBy('type');

            $counts = Area::query()
                ->join('area_hill', 'area_hill.area_id', '=', 'areas.id')
                ->groupBy('areas.code')
                ->selectRaw('areas.code as code, count(*) as aggregate')
                ->pluck('aggregate', 'code');

            $grouped = [];

            foreach (config('dobih.area_types') as $type => $label) {
                foreach ($areas[$type] ?? [] as $area) {
                    $grouped[$label][] = [
                        'code' => $area->code,
                        'name' => $area->name,
                        'count' => (int) ($counts[$area->code] ?? 0),
                    ];
                }
            }

            return $grouped;
        });
    }

    /** The name of one area, or null if the code isn't one. */
    public static function name(string $code): ?string
    {
        return $code === '' ? null : Area::query()->where('code', $code)->value('name');
    }
}
