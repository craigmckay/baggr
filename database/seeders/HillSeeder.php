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
                'walkhighlands_url' => $row[$columns['WH URL']],
                'mwis_forecast_url' => $row[$columns['MWIS Forecast URL']],
                'grid_ref' => $row[$columns['Grid Ref']],
                'latitude' => $row[$columns['Latitude']],
                'longitude' => $row[$columns['Longitude']],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        // The photo columns (photo_url, photo_page_url, photo_title,
        // photo_author, photo_geograph_id) are deliberately left out of the
        // update list below: they're populated separately from Geograph by
        // `hills:fetch-photos` and shouldn't be wiped out by re-running this
        // seeder.
        foreach (array_chunk($hills, 100) as $chunk) {
            Hill::upsert($chunk, ['dobih_number'], [
                'name', 'slug', 'height_m', 'walkhighlands_url', 'mwis_forecast_url',
                'grid_ref', 'latitude', 'longitude', 'updated_at',
            ]);
        }
    }
}
