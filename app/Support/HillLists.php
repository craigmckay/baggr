<?php

namespace App\Support;

use App\Models\Hill;

/**
 * The hill lists Baggr tracks, from most to least significant (the order of
 * config('dobih.lists'): Munro, Munro Top, Corbett and so on). A hill on several
 * lists is shown under the most significant (spec §3).
 */
class HillLists
{
    /**
     * The codes of the lists, most significant first.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        // A list code is a string, but PHP turns a numeric-looking array key into an int.
        return array_map(strval(...), array_keys(config('dobih.lists')));
    }

    /**
     * The list a hill is shown under, or null if it is on none of them. Needs the
     * hill's classes loaded, so a page of hills can load them once.
     *
     * @return array{code: string, name: string}|null
     */
    public static function mainFor(Hill $hill): ?array
    {
        // The class's own name is the singular DoBIH gives it: "Munro Top", not "Munro Tops".
        $held = $hill->classes->pluck('name', 'code');

        foreach (self::codes() as $code) {
            if ($held->has($code)) {
                return ['code' => $code, 'name' => $held[$code]];
            }
        }

        return null;
    }

    /** SQL for a hill's significance: 1 for the most significant list, 99 for a hill on none. */
    public static function rankSql(): string
    {
        $codes = self::codes();
        $ranks = collect($codes)->map(fn (string $code, int $index) => "when '{$code}' then ".($index + 1))->implode(' ');
        $in = collect($codes)->map(fn (string $code) => "'{$code}'")->implode(', ');

        return "coalesce((select min(case hc.code {$ranks} end) from class_hill ch"
            .' join hill_classes hc on hc.id = ch.hill_class_id'
            ." where ch.hill_id = hills.id and hc.code in ({$in})), 99)";
    }
}
