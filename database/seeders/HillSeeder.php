<?php

namespace Database\Seeders;

use App\Models\Hill;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class HillSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Import all Munros from the Walkhighlands/MWIS match spreadsheet.
     */
    public function run(): void
    {
        $sheet = IOFactory::load(base_path('data/munro_walkhighlands_match.xlsx'))
            ->getSheetByName('Munro match');

        $rows = $sheet->toArray(null, true, true, true);
        $header = array_shift($rows);

        $columns = array_flip($header);

        $hills = collect($rows)
            ->filter(fn (array $row) => filled($row[$columns['WH Name']]))
            ->map(fn (array $row) => [
                'name' => $row[$columns['WH Name']],
                'slug' => $row[$columns['WH Slug']],
                'dobih_number' => $row[$columns['DoBIH Number']],
                'height_m' => $row[$columns['WH Height (m)']],
                'region' => $row[$columns['MWIS Area Name']],
                'walkhighlands_url' => $row[$columns['WH URL']],
                'mwis_forecast_url' => $row[$columns['MWIS Forecast URL']],
                'grid_ref' => $row[$columns['Grid Ref']],
                'latitude' => $row[$columns['Latitude']],
                'longitude' => $row[$columns['Longitude']],
                'smc_section' => $row[$columns['SMC Section']],
                'smc_section_description' => $row[$columns['SMC Section Description']],
                'rhb_section' => $row[$columns['RHB Section']],
                'rhb_section_description' => $row[$columns['RHB Section Description']],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        // photo_url is deliberately left out of the update list below: it's
        // populated separately by `hills:fetch-photos` and shouldn't be wiped
        // out by re-running this seeder.
        foreach (array_chunk($hills, 100) as $chunk) {
            Hill::upsert($chunk, ['name'], [
                'slug', 'dobih_number', 'height_m', 'region', 'walkhighlands_url', 'mwis_forecast_url',
                'grid_ref', 'latitude', 'longitude', 'smc_section', 'smc_section_description',
                'rhb_section', 'rhb_section_description', 'updated_at',
            ]);
        }
    }
}
