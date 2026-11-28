<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Works out each hill's MWIS area (spec §3), in this order:
 *
 *  1. the area MWIS already forecasts for it (the Munros' stored forecast link)
 *  2. its DoBIH named area (the Lake District fells, the Welsh ranges)
 *  3. its Murray section, or for a section that straddles two MWIS areas, the
 *     area of the nearest Munro
 *
 * The section and named-area mappings are also written to areas.mwis_area, the
 * mapping table. Hills with no match keep no area.
 */
class MwisAreaMapper
{
    /**
     * @return array{mapped: int, nearest: int, none: int}
     */
    public function run(): array
    {
        $this->writeAreaMapping();

        DB::table('hills')->update(['mwis_area' => null]);

        foreach (MwisAreas::all() as $area) {
            DB::table('hills')
                ->whereNotNull('mwis_forecast_url')
                ->where('mwis_forecast_url', 'like', '%/'.$area['slug'])
                ->update(['mwis_area' => $area['code']]);
        }

        foreach (['named_area', 'murray_section'] as $type) {
            DB::table('hills')
                ->whereNull('mwis_area')
                ->update(['mwis_area' => DB::raw(
                    '(select areas.mwis_area from area_hill'
                    .' join areas on areas.id = area_hill.area_id'
                    ." where area_hill.hill_id = hills.id and areas.type = '{$type}' and areas.mwis_area is not null"
                    .' limit 1)'
                )]);
        }

        $nearest = $this->mapStraddlingSections();

        // An earlier migration runs this before the column is there.
        if (Schema::hasColumn('hills', 'mwis_nearest')) {
            $this->markNearestAreas();
        }

        return [
            'mapped' => DB::table('hills')->whereNotNull('mwis_area')->count(),
            'nearest' => $nearest,
            'none' => DB::table('hills')->whereNull('mwis_area')->count(),
        ];
    }

    /**
     * Marks the hills whose MWIS area is only the nearest one: MWIS's description
     * of the area doesn't take them in (see config/mwis.php).
     *
     * @return int how many were marked
     */
    public function markNearestAreas(): int
    {
        $hillsIn = fn (string $type, array $codes) => DB::table('area_hill')
            ->join('areas', 'areas.id', '=', 'area_hill.area_id')
            ->where('areas.type', $type)
            ->whereIn('areas.code', $codes)
            ->select('area_hill.hill_id');

        DB::table('hills')->update(['mwis_nearest' => false]);

        return DB::table('hills')
            ->whereNotNull('mwis_area')
            ->whereNull('mwis_forecast_url')
            ->whereIn('id', $hillsIn('murray_section', config('mwis.nearest_area_sections')))
            ->whereNotIn('id', $hillsIn('named_area', array_keys(config('mwis.named_areas'))))
            ->whereNotIn('id', $hillsIn('hill_range', config('mwis.claimed_hill_ranges')))
            ->whereNotIn('id', $hillsIn('rhb_section', config('mwis.claimed_rhb_sections')))
            ->update(['mwis_nearest' => true]);
    }

    private function writeAreaMapping(): void
    {
        DB::table('areas')->whereIn('type', ['named_area', 'murray_section'])->update(['mwis_area' => null]);

        foreach (config('mwis.named_areas') as $code => $mwis) {
            DB::table('areas')->where('type', 'named_area')->where('code', $code)->update(['mwis_area' => $mwis]);
        }

        foreach (config('mwis.murray_sections') as $code => $mwis) {
            DB::table('areas')->where('type', 'murray_section')->where('code', $code)->update(['mwis_area' => $mwis]);
        }
    }

    /**
     * Hills in a section that straddles two MWIS areas take the area of the
     * nearest Munro.
     */
    private function mapStraddlingSections(): int
    {
        $munros = DB::table('hills')
            ->whereNotNull('mwis_forecast_url')
            ->whereNotNull('mwis_area')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude', 'mwis_area']);

        if ($munros->isEmpty()) {
            return 0;
        }

        $hills = DB::table('hills')
            ->join('area_hill', 'area_hill.hill_id', '=', 'hills.id')
            ->join('areas', 'areas.id', '=', 'area_hill.area_id')
            ->where('areas.type', 'murray_section')
            ->whereIn('areas.code', config('mwis.nearest_munro_sections'))
            ->whereNull('hills.mwis_forecast_url')
            ->whereNotNull('hills.latitude')
            ->whereNotNull('hills.longitude')
            ->get(['hills.id', 'hills.latitude', 'hills.longitude']);

        $byArea = [];

        foreach ($hills as $hill) {
            $best = null;
            $bestDistance = INF;

            foreach ($munros as $munro) {
                // Close enough over a few tens of kilometres, and cheap.
                $dLat = $hill->latitude - $munro->latitude;
                $dLon = ($hill->longitude - $munro->longitude) * cos(deg2rad((float) $hill->latitude));
                $distance = $dLat * $dLat + $dLon * $dLon;

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $munro->mwis_area;
                }
            }

            $byArea[$best][] = $hill->id;
        }

        foreach ($byArea as $code => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('hills')->whereIn('id', $chunk)->update(['mwis_area' => $code]);
            }
        }

        return $hills->count();
    }
}
