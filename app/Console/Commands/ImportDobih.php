<?php

namespace App\Console\Commands;

use Generator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('dobih:import {path? : Folder of CSVs written by database/dobih/export-dobih.ps1 (default storage/app/dobih)}')]
#[Description('Import the Database of British and Irish Hills (CC BY 4.0) from CSVs exported from its Access file')]
class ImportDobih extends Command
{
    /** The first letter of a DoBIH area code says what kind of area it is. */
    private const AREA_TYPES = [
        'A' => 'historic_county',
        'C' => 'county',
        'D' => 'region',
        'I' => 'island',
        'L' => 'london_borough',
        'M' => 'murray_section',
        'N' => 'named_area',
        'R' => 'rhb_section',
        'U' => 'authority',
    ];

    private const FILES = ['Hills', 'Hillnames', 'Areas', 'Arealink', 'Class', 'Classlink', 'Maps', 'Mapslink'];

    /** Links that point at a hill, area, class or map sheet the source itself doesn't list. */
    private int $skipped = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = rtrim((string) ($this->argument('path') ?? storage_path('app/dobih')), '/\\');

        foreach (self::FILES as $name) {
            if (! is_file("{$path}/{$name}.csv")) {
                $this->error("Missing {$path}/{$name}.csv. Run database/dobih/export-dobih.ps1 first.");

                return self::FAILURE;
            }
        }

        $counts = DB::transaction(fn () => [
            'hills' => $this->importHills($path),
            'alternate names' => $this->importNames($path),
            'areas' => $this->importAreas($path),
            'area links' => $this->importAreaLinks($path),
            'classes' => $this->importClasses($path),
            'class links' => $this->importClassLinks($path),
            'os maps' => $this->importMaps($path),
            'map links' => $this->importMapLinks($path),
        ]);

        foreach ($counts as $label => $count) {
            $this->line(sprintf('%-16s %s', $label, number_format($count)));
        }

        if ($this->skipped > 0) {
            $this->warn("Skipped {$this->skipped} links to a hill, area, class or map sheet missing from the source.");
        }

        $this->info('DoBIH import finished. Version in config/dobih.php is '.config('dobih.version').'.');

        return self::SUCCESS;
    }

    /**
     * Hills already in Baggr (the Munros) keep their name, slug, links, photo and heights;
     * only the DoBIH fields are filled in. Every other hill is added.
     */
    private function importHills(string $path): int
    {
        $baseSlugs = [];
        foreach ($this->rows("{$path}/Hills.csv") as $row) {
            $baseSlugs[(int) $row['Hillnumber']] = Str::slug($row['Hillname']) ?: 'hill';
        }
        $baseCounts = array_count_values($baseSlugs);

        $existing = DB::table('hills')->pluck('id', 'dobih_number')->all();
        $usedSlugs = array_flip(DB::table('hills')->pluck('slug')->all());

        $now = now();
        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Hills.csv") as $row) {
            $number = (int) $row['Hillnumber'];
            $fields = $this->hillFields($row);
            $total++;

            if (isset($existing[$number])) {
                DB::table('hills')->where('id', $existing[$number])->update($fields);

                continue;
            }

            $base = $baseSlugs[$number];
            $slug = ($baseCounts[$base] > 1 || isset($usedSlugs[$base])) ? "{$base}-{$number}" : $base;
            $usedSlugs[$slug] = true;

            $metres = $this->decimal($row['Metres']);

            $batch[] = $fields + [
                'dobih_number' => $number,
                'name' => $row['Hillname'],
                'slug' => $slug,
                'height_m' => $metres === null ? 0 : (int) round($metres),
                'grid_ref' => $this->text($row['Gridref']),
                'latitude' => $this->decimal($row['Latitude']),
                'longitude' => $this->decimal($row['Longitude']),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('hills')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('hills')->insert($batch);
        }

        return $total;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function hillFields(array $row): array
    {
        $parentMarilyn = $this->integer($row['ParentMa']);

        return [
            'country' => $this->text($row['Country']),
            'metres' => $this->decimal($row['Metres']),
            'feet' => $this->integer($row['Feet']),
            'drop_m' => $this->decimal($row['Drop']),
            'col_grid_ref' => $this->text($row['Colgridref']),
            'col_height_m' => $this->decimal($row['Colheight']),
            'grid_ref_10' => $this->text($row['Gridref10']),
            'easting' => $this->integer($row['Xcoord']),
            'northing' => $this->integer($row['Ycoord']),
            'dobih_section' => $this->text($row['_Section']),
            'classification' => $this->text($row['Classification']),
            'parent_marilyn_number' => $parentMarilyn === 0 ? null : $parentMarilyn,
            'parent_smc_number' => $this->integer($row['ParentSMC']),
            'feature' => $this->text($row['Feature']),
            'observations' => $this->text($row['Observations'], 500),
            'survey' => $this->text($row['Survey']),
            'comments' => $this->text($row['Comments'], 500),
            'revised_on' => $this->date($row['Revision']),
            'tump_only' => $row['Tumponly'] === '1',
            'mv_number' => $this->integer($row['MVNumber']),
        ];
    }

    private function importNames(string $path): int
    {
        $hillIds = DB::table('hills')->pluck('id', 'dobih_number')->all();

        DB::table('hill_names')->delete();

        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Hillnames.csv") as $row) {
            $hillId = $hillIds[(int) $row['Hillnumber']] ?? null;

            if ($hillId === null || trim($row['Hillname']) === '') {
                continue;
            }

            $batch[] = ['hill_id' => $hillId, 'name' => $row['Hillname'], 'sort_order' => (int) $row['Sortseq']];
            $total++;

            if (count($batch) >= 1000) {
                DB::table('hill_names')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('hill_names')->insert($batch);
        }

        return $total;
    }

    private function importAreas(string $path): int
    {
        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Areas.csv") as $row) {
            $code = trim($row['Arearef']);

            // DoBIH's own "(All)" row isn't an area.
            if ($code === '' || str_starts_with($code, '(')) {
                continue;
            }

            $batch[] = [
                'code' => $code,
                'short_name' => $row['Shortname'] !== '' ? $row['Shortname'] : $row['Areaname'],
                'name' => $row['Areaname'],
                'type' => self::AREA_TYPES[$code[0]] ?? 'other',
                'country' => $this->text($row['Country']),
            ];
            $total++;
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('areas')->upsert($chunk, ['code'], ['short_name', 'name', 'type', 'country']);
        }

        return $total;
    }

    private function importAreaLinks(string $path): int
    {
        $hillIds = DB::table('hills')->pluck('id', 'dobih_number')->all();
        $areaIds = DB::table('areas')->pluck('id', 'code')->all();

        DB::table('area_hill')->delete();

        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Arealink.csv") as $row) {
            $hillId = $hillIds[(int) $row['Hillnumber']] ?? null;
            $areaId = $areaIds[trim($row['Arearef'])] ?? null;

            if ($hillId === null || $areaId === null) {
                $this->skipped++;

                continue;
            }

            $batch[] = [
                'area_id' => $areaId,
                'hill_id' => $hillId,
                'is_alt' => $row['Alt_Area'] === '1',
                'is_county_top' => $row['Ctop'] === '1',
            ];
            $total++;

            if (count($batch) >= 2000) {
                DB::table('area_hill')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('area_hill')->insert($batch);
        }

        return $total;
    }

    private function importClasses(string $path): int
    {
        $batch = [];

        foreach ($this->rows("{$path}/Class.csv") as $row) {
            $batch[] = [
                'code' => trim($row['Classref']),
                'name' => $row['Classname'],
                'show_in_list' => $row['Showinlist'] === '1',
                'sort_order' => (int) $row['SortSeq'],
            ];
        }

        DB::table('hill_classes')->upsert($batch, ['code'], ['name', 'show_in_list', 'sort_order']);

        return count($batch);
    }

    private function importClassLinks(string $path): int
    {
        $hillIds = DB::table('hills')->pluck('id', 'dobih_number')->all();
        $classIds = DB::table('hill_classes')->pluck('id', 'code')->all();

        DB::table('class_hill')->delete();

        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Classlink.csv") as $row) {
            $hillId = $hillIds[(int) $row['Hillnumber']] ?? null;
            $classId = $classIds[trim($row['Classref'])] ?? null;

            if ($hillId === null || $classId === null) {
                $this->skipped++;

                continue;
            }

            $batch[] = ['hill_class_id' => $classId, 'hill_id' => $hillId];
            $total++;

            if (count($batch) >= 2000) {
                DB::table('class_hill')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('class_hill')->insert($batch);
        }

        return $total;
    }

    private function importMaps(string $path): int
    {
        $batch = [];

        foreach ($this->rows("{$path}/Maps.csv") as $row) {
            $batch[] = ['type' => trim($row['Maptype']), 'ref' => trim($row['Mapref']), 'name' => $row['Mapname']];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('os_maps')->upsert($chunk, ['type', 'ref'], ['name']);
        }

        return count($batch);
    }

    private function importMapLinks(string $path): int
    {
        $hillIds = DB::table('hills')->pluck('id', 'dobih_number')->all();
        $mapIds = [];
        foreach (DB::table('os_maps')->get(['id', 'type', 'ref']) as $map) {
            $mapIds["{$map->type}|{$map->ref}"] = $map->id;
        }

        DB::table('hill_map')->delete();

        $batch = [];
        $total = 0;

        foreach ($this->rows("{$path}/Mapslink.csv") as $row) {
            $hillId = $hillIds[(int) $row['Hillnumber']] ?? null;
            $mapId = $mapIds[trim($row['Maptype']).'|'.trim($row['Mapref'])] ?? null;

            if ($hillId === null || $mapId === null) {
                $this->skipped++;

                continue;
            }

            $batch[] = ['hill_id' => $hillId, 'os_map_id' => $mapId];
            $total++;

            if (count($batch) >= 2000) {
                DB::table('hill_map')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('hill_map')->insert($batch);
        }

        return $total;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function rows(string $file): Generator
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$file}.");
        }

        $header = fgetcsv($handle, null, ',', '"', '');

        if ($header === false) {
            throw new RuntimeException("{$file} is empty.");
        }

        $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }

            yield array_combine($header, array_pad($row, count($header), ''));
        }

        fclose($handle);
    }

    private function text(string $value, int $limit = 255): ?string
    {
        $value = trim($value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    private function integer(string $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function decimal(string $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 6) : null;
    }

    private function date(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : Carbon::parse($value)->toDateString();
    }
}
