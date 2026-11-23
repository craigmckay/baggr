<?php

namespace App\Enums;

use App\Models\Area;
use App\Models\Hill;
use App\Support\MwisAreas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The three ways hills are grouped into regions (spec §3).
 *
 * MWIS is the hill's forecast area. SMC and RHB are the Murray section and
 * sub-section DoBIH gives every hill (for example 4 and 04B). The Munros' own
 * stored values win where they exist, and they are the same thing.
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
     * Where a hill falls in this grouping. Hills with nothing to go on (Ireland,
     * the Northern and Western Isles and so on, for MWIS) go under "No area" or
     * "Not mapped yet", which always comes last.
     *
     * @return array{key: string, label: string, sort: string}
     */
    public function sectionFor(Hill $hill): array
    {
        $section = match ($this) {
            self::Mwis => $this->mwisSection($hill),
            self::Smc => $this->smcSection($hill),
            self::Rhb => $this->rhbSection($hill),
        };

        return $section ?? [
            'key' => 'none',
            'label' => $this === self::Mwis ? __('No MWIS area') : __('Not mapped yet'),
            'sort' => 'zzz',
        ];
    }

    /**
     * The order hills are listed in when they are grouped, as SQL, so a page of
     * the hill list can show whole sections together.
     */
    public function orderSql(): string
    {
        return match ($this) {
            self::Mwis => 'case '.collect(MwisAreas::all())
                ->map(fn (array $area) => "when hills.mwis_area = '{$area['code']}' then {$area['order']}")
                ->implode(' ').' else 99 end',
            // "4.2" is section 4, sub-section B. Sub-sections run A to F, so one decimal place sorts them.
            self::Smc, self::Rhb => 'cast(hills.dobih_section as decimal(6, 2))',
        };
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function mwisSection(Hill $hill): ?array
    {
        $area = $hill->mwisArea();

        if ($area === null) {
            return null;
        }

        return [
            'key' => 'mwis-'.$area['code'],
            'label' => $area['name'],
            'sort' => sprintf('%02d', $area['order']),
        ];
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function smcSection(Hill $hill): ?array
    {
        if (trim((string) $hill->smc_section) !== '') {
            return $this->numberedSection($hill->smc_section, $hill->smc_section_description, pad: true);
        }

        $top = $this->sectionNumbers($hill)['top'] ?? null;

        if ($top === null) {
            return null;
        }

        $code = sprintf('M%02d', $top);

        return $this->numberedSection($top, $this->sectionName($code), pad: true);
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function rhbSection(Hill $hill): ?array
    {
        if (trim((string) $hill->rhb_section) !== '') {
            return $this->numberedSection($hill->rhb_section, $hill->rhb_section_description, pad: false);
        }

        $numbers = $this->sectionNumbers($hill);

        if ($numbers === null) {
            return null;
        }

        $number = sprintf('%02d', $numbers['top']).$numbers['letter'];

        return $this->numberedSection($number, $this->sectionName('M'.$number), pad: false);
    }

    /**
     * Reads DoBIH's "4.2" (section 4, sub-section B) from the hill.
     *
     * @return array{top: int, letter: string}|null
     */
    private function sectionNumbers(Hill $hill): ?array
    {
        $section = trim((string) $hill->dobih_section);

        if ($section === '' || ! preg_match('/^(\d+)(?:\.(\d+))?$/', $section, $match)) {
            return null;
        }

        $sub = (int) ($match[2] ?? 0);

        return ['top' => (int) $match[1], 'letter' => $sub >= 1 && $sub <= 26 ? chr(64 + $sub) : ''];
    }

    /** The text after the colon in "04B: Loch Treig to Loch Ericht". */
    private function sectionName(string $code): ?string
    {
        // Read once per request (the array store lives only as long as the app does).
        $names = Cache::store('array')->rememberForever(
            'murray-section-names',
            fn () => Area::query()->where('type', 'murray_section')->pluck('name', 'code')->all(),
        );

        $name = $names[$code] ?? null;

        return $name === null ? null : trim(Str::after($name, ':'));
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
