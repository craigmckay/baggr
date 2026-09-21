<?php

namespace App\Console\Commands;

use App\Models\Hill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('hills:fetch-photos {--force : Re-fetch even for hills that already have a photo}')]
#[Description("Scrape each hill's Walkhighlands page for its main photo URL")]
class FetchHillPhotos extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hills = Hill::query()
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('photo_url'))
            ->get();

        if ($hills->isEmpty()) {
            $this->info('Nothing to fetch.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($hills->count());
        $bar->start();

        $failures = [];

        foreach ($hills as $hill) {
            try {
                $response = Http::withUserAgent('Baggr/1.0 (+https://baggr.co.uk; hill photo lookup)')
                    ->timeout(15)
                    ->get($hill->walkhighlands_url);

                if ($response->successful() && preg_match(
                    '/<img[^>]+src="([^"]*\/munros\/[^"]+\.(?:jpe?g|png))"/i',
                    $response->body(),
                    $matches
                )) {
                    $url = $matches[1];

                    if (! str_starts_with($url, 'http')) {
                        $url = 'https://www.walkhighlands.co.uk'.$url;
                    }

                    $hill->update(['photo_url' => $url]);
                } else {
                    $failures[] = $hill->name;
                }
            } catch (\Throwable) {
                $failures[] = $hill->name;
            }

            $bar->advance();
            usleep(300_000);
        }

        $bar->finish();
        $this->newLine();

        if ($failures !== []) {
            $this->warn('No photo found for: '.implode(', ', $failures));
        }

        return self::SUCCESS;
    }
}
