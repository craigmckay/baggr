<?php

namespace App\Enums;

use App\Models\Area;
use App\Models\Hill;
use App\Support\MwisAreas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The three ways hills are put into sections (spec §3).
 *
 * A hill's MWIS area is the forecast area it falls in. Its Murray section and
 * sub-section (for example 4 and 04B) are worked out from DoBIH's section for
 * the hill ("4.2" is section 4, sub-section B), which every hill has.
 */
enum HillGrouping: string
{
    case Mwis = 'mwis';
    case MurraySection = 'murray';
    case MurraySubsection = 'murray-sub';

    public function label(): string
    {
        return match ($this) {
            self::Mwis => __('MWIS area'),
            self::MurraySection => __('Murray section'),
            self::MurraySubsection => __('Murray sub-section'),
        };
    }

    /**
     * Where a hill falls in this grouping. Hills with nothing to go on (Ireland,
     * the Northern and Western Isles and so on, for MWIS) go under "No MWIS area"
     * or "Not mapped yet", which always comes last.
     *
     * @return array{key: string, label: string, sort: string}
     */
    public function sectionFor(Hill $hill): array
    {
        $section = match ($this) {
            self::Mwis => $this->mwisSection($hill),
            self::MurraySection => $this->murraySection($hill),
            self::MurraySubsection => $this->murraySubsection($hill),
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
            self::MurraySection, self::MurraySubsection => 'cast(hills.dobih_section as decimal(6, 2))',
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
    private function murraySection(Hill $hill): ?array
    {
        $top = self::sectionNumbers($hill->dobih_section)['top'] ?? null;

        if ($top === null) {
            return null;
        }

        return $this->numberedSection((string) $top, self::sectionName(sprintf('M%02d', $top)), pad: true);
    }

    /**
     * @return array{key: string, label: string, sort: string}|null
     */
    private function murraySubsection(Hill $hill): ?array
    {
        $numbers = self::sectionNumbers($hill->dobih_section);

        if ($numbers === null) {
            return null;
        }

        $number = sprintf('%02d', $numbers['top']).$numbers['letter'];

        return $this->numberedSection($number, self::sectionName('M'.$number), pad: false);
    }

    /**
     * Reads DoBIH's "4.2" (section 4, sub-section B) for a hill.
     *
     * @return array{top: int, letter: string}|null
     */
    public static function sectionNumbers(?string $dobihSection): ?array
    {
        $section = trim((string) $dobihSection);

        if ($section === '' || ! preg_match('/^(\d+)(?:\.(\d+))?$/', $section, $match)) {
            return null;
        }

        $sub = (int) ($match[2] ?? 0);

        return ['top' => (int) $match[1], 'letter' => $sub >= 1 && $sub <= 26 ? chr(64 + $sub) : ''];
    }

    /** The text after the colon in "04B: Loch Treig to Loch Ericht". */
    private static function sectionName(string $code): ?string
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
     * @return array{key: string, label: string, sort: string}
     */
    private function numberedSection(string $number, ?string $description, bool $pad): array
    {
        return [
            'key' => $this->value.'-'.$number,
            'label' => trim($number.'. '.$description),
            'sort' => $pad ? str_pad($number, 5, '0', STR_PAD_LEFT) : $number,
        ];
    }
}
