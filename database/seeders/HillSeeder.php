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
                'height_m' => $row[$columns['WH Height (m)']],
                'region' => $row[$columns['MWIS Area Name']],
                'walkhighlands_url' => $row[$columns['WH URL']],
                'mwis_forecast_url' => $row[$columns['MWIS Forecast URL']],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        foreach (array_chunk($hills, 100) as $chunk) {
            Hill::upsert($chunk, ['name'], ['height_m', 'region', 'walkhighlands_url', 'mwis_forecast_url', 'updated_at']);
        }
    }
}
