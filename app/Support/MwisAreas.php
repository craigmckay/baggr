<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Looks up the ten MWIS forecast areas in config/mwis.php (spec §3 and §4).
 *
 * @phpstan-type MwisArea array{slug: string, code: string, name: string, url: string, order: int}
 */
class MwisAreas
{
    /**
     * @return array<string, MwisArea> Keyed by area code, in display order
     */
    public static function all(): array
    {
        $areas = [];
        $order = 0;

        foreach (config('mwis.areas') as $slug => $area) {
            $areas[$area['code']] = [
                'slug' => $slug,
                'code' => $area['code'],
                'name' => $area['name'],
                'url' => rtrim(config('mwis.base_url'), '/').'/'.$area['path'].'/'.$slug,
                'order' => $order++,
            ];
        }

        return $areas;
    }

    /**
     * @return MwisArea|null
     */
    public static function find(?string $code): ?array
    {
        return $code === null ? null : (self::all()[$code] ?? null);
    }

    /**
     * The area a forecast URL belongs to, such as the one stored for each Munro.
     *
     * @return MwisArea|null
     */
    public static function fromUrl(?string $url): ?array
    {
        $slug = Str::afterLast(rtrim((string) $url, '/'), '/');

        foreach (self::all() as $area) {
            if ($area['slug'] === $slug) {
                return $area;
            }
        }

        return null;
    }
}
