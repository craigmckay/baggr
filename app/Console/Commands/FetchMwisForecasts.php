<?php

namespace App\Console\Commands;

use App\Models\MwisForecast;
use App\Support\Mwis\ForecastPageParser;
use App\Support\MwisAreas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

#[Signature('mwis:fetch')]
#[Description('Fetch the latest MWIS forecast for every area (one request each)')]
class FetchMwisForecasts extends Command
{
    public function handle(ForecastPageParser $parser): int
    {
        if (! config('mwis.enabled')) {
            $this->info('MWIS forecasts are switched off (MWIS_FORECASTS). Nothing was fetched.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach (array_values(MwisAreas::all()) as $index => $area) {
            if ($index > 0) {
                Sleep::for(1)->second();
            }

            try {
                $page = Http::withUserAgent(config('mwis.user_agent'))->timeout(30)->get($area['url'])->throw()->body();
                $days = $parser->parse($page);

                // Replaced only once the new forecast has been read, so a failure keeps the last good one.
                DB::transaction(function () use ($area, $days) {
                    MwisForecast::query()->where('area', $area['code'])->delete();

                    foreach ($days as $day) {
                        MwisForecast::create(['area' => $area['code']] + $day);
                    }
                });

                $this->line(sprintf('%-36s %d days', $area['name'], count($days)));
            } catch (Throwable $e) {
                $failed++;
                Log::error("MWIS forecast for {$area['name']} was not updated: {$e->getMessage()}");
                $this->error(sprintf('%-36s failed: %s', $area['name'], $e->getMessage()));
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
