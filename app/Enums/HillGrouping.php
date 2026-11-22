<?php

namespace App\Enums;

use App\Models\Hill;
use Illuminate\Support\Str;

/**
 * The three ways hills are grouped into regions (spec §3).
 */
enum HillGrouping: string
{
    case Mwis = 'mwis';
    case Smc = 'smc';
    case Rhb = 'rhb';

    public function label(): string
    {
        return match ($this) {
            self::Mwis => __('MWIS area'),
            self::Smc => __('SMC section'),
            self::Rhb => __('RHB section'),
        };
    }

    /**
     * Where a hill falls in this grouping. Hills with nothing mapped (only the
     * Munros have MWIS, SMC and RHB values so far) go under "Not mapped yet",
     * which always comes last.
     *
     * @return array{key: string, label: string, sort: string}
     */
    public function sectionFor(Hill $hill): array
    {
        $section = match ($this) {
            self::Mwis => $this->mwisSection($hill),
            self::Smc => $this->numberedSection($hill->smc_section, $hill->smc_section_description, pad: true),
            self::Rhb => $this->numberedSection($hill->rhb_section, $hill->rhb_section_description, pad: false),
        };

        return $section ?? ['key' => 'none', 'label' => __('Not mapped yet'), 'sort' => 'zzz'];
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function mwisSection(Hill $hill): ?array
    {
        $slug = Str::afterLast(rtrim((string) $hill->mwis_forecast_url, '/'), '/');
        $areas = config('mwis.areas');

        if ($slug === '' || ! isset($areas[$slug])) {
            return null;
        }

        return [
            'key' => 'mwis-'.$areas[$slug]['code'],
            'label' => $areas[$slug]['name'],
            'sort' => sprintf('%02d', array_search($slug, array_keys($areas), true)),
        ];
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function numberedSection(mixed $number, ?string $description, bool $pad): ?array
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        return [
            'key' => $this->value.'-'.$number,
            'label' => trim($number.'. '.$description),
            'sort' => $pad ? str_pad($number, 5, '0', STR_PAD_LEFT) : $number,
        ];
    }
}
