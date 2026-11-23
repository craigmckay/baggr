<?php

namespace App\Support;

use App\Models\Area;
use Illuminate\Support\Facades\Cache;

/**
 * The DoBIH areas a person can pick to narrow a hill list (spec §3). A person
 * picks the kind of area first (region, Murray section, council area...) and
 * then one of that kind, so the lists are grouped by type, each area with the
 * number of hills in it. Areas with no hills are left out.
 */
class AreaOptions
{
    public const CACHE_KEY = 'area-options.v2';

    /**
     * @return array<string, array{label: string, areas: list<array{code: string, name: string, count: int}>}> Type => its label and areas
     */
    public static function grouped(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
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
                    $grouped[$type]['label'] = $label;
                    $grouped[$type]['areas'][] = [
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

    /** The kind of area a code is ("region", "murray_section"...), or an empty string if it isn't one. */
    public static function typeOf(string $code): string
    {
        return $code === '' ? '' : (string) Area::query()->where('code', $code)->value('type');
    }
}
