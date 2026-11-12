<?php

namespace App\Console\Commands;

use App\Models\Hill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

#[Signature('hills:fetch-photos {--force : Re-fetch even for hills that already have a photo}')]
#[Description('Find a summit photo for each hill on Geograph (CC BY-SA 2.0) by grid reference')]
class FetchHillPhotos extends Command
{
    private const ENDPOINT = 'https://api.geograph.org.uk/syndicator.php';

    private const USER_AGENT = 'Baggr/1.0 (+https://baggr.co.uk; hill photo lookup)';

    /** Pause between API requests, to stay well within polite use. */
    private const THROTTLE_MS = 1000;

    /**
     * Photos further than this from the summit are ignored. Geograph falls
     * back to a place-name search when it can't parse a location, so this
     * also guards against results from somewhere else entirely.
     */
    private const MAX_DISTANCE_M = 1500;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = config('services.geograph.key');

        if (blank($key)) {
            $this->error('No Geograph API key set. Add GEOGRAPH_API_KEY to your .env file.');

            return self::FAILURE;
        }

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

        foreach ($hills as $index => $hill) {
            if ($index > 0) {
                Sleep::for(self::THROTTLE_MS)->milliseconds();
            }

            try {
                $response = Http::withUserAgent(self::USER_AGENT)
                    ->acceptJson()
                    ->timeout(20)
                    ->get(self::ENDPOINT, [
                        'key' => $key,
                        'format' => 'JSON',
                        'location' => $hill->grid_ref,
                        'distance' => 1,
                    ]);

                $photo = $response->successful()
                    ? $this->bestPhoto($response->json('items') ?? [], $hill)
                    : null;

                if ($photo !== null) {
                    $hill->update($photo);
                } else {
                    $failures[] = $hill->name;
                }
            } catch (\Throwable) {
                $failures[] = $hill->name;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($failures !== []) {
            $this->warn('No photo found for: '.implode(', ', $failures));
        }

        return self::SUCCESS;
    }

    /**
     * Pick the most summit-like photo from a Geograph feed and map it to
     * the hill's photo columns.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>|null
     */
    private function bestPhoto(array $items, Hill $hill): ?array
    {
        $best = null;
        $bestScore = null;

        foreach ($items as $item) {
            $photo = is_array($item) ? $this->normalise($item) : null;

            if ($photo === null) {
                continue;
            }

            $distance = $this->distanceInMetres($item, $hill);

            if ($distance !== null && $distance > self::MAX_DISTANCE_M) {
                continue;
            }

            $score = $this->score($photo['photo_title'], (string) ($item['tags'] ?? ''), $distance, $hill);

            // Strictly greater: the feed is newest-first, so ties go to the newer photo.
            if ($bestScore === null || $score > $bestScore) {
                $best = $photo;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Map a feed item to the hill's photo columns, or null if it isn't a
     * usable CC BY-SA 2.0 Geograph photo.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalise(array $item): ?array
    {
        $id = (string) ($item['guid'] ?? '');
        $thumb = (string) ($item['thumb'] ?? '');
        $author = trim((string) ($item['author'] ?? ''));
        $licence = (string) ($item['licence'] ?? '');

        if (! ctype_digit($id) || $author === '' || ! str_contains($licence, 'creativecommons.org/licenses/by-sa/2.0')) {
            return null;
        }

        // The feed only carries a 120px thumbnail; the standard-size image
        // lives at the same URL without the size suffix.
        if (! preg_match('#^https://s\d+\.geograph\.org\.uk/.+_\d+x\d+\.jpe?g$#i', $thumb)) {
            return null;
        }

        $link = (string) ($item['link'] ?? '');

        return [
            'photo_url' => preg_replace('/_\d+x\d+(\.jpe?g)$/i', '$1', $thumb),
            'photo_page_url' => str_starts_with($link, 'https://www.geograph.org.uk/photo/')
                ? $link
                : "https://www.geograph.org.uk/photo/{$id}",
            // Titles arrive prefixed with the grid square, e.g. "NN1671 : Ben Nevis summit".
            'photo_title' => Str::limit(trim(preg_replace('/^[A-Z]{1,2}\d{4}\s*:\s*/', '', (string) ($item['title'] ?? ''))), 250),
            'photo_author' => Str::limit($author, 250),
            'photo_geograph_id' => (int) $id,
        ];
    }

    /**
     * Higher is better: favour photos that are of the summit itself, name
     * the hill, and were taken close to the summit.
     */
    private function score(string $title, string $tags, ?float $distance, Hill $hill): float
    {
        $title = Str::lower($title);
        $tags = Str::lower($tags);
        $score = 0.0;

        if (str_contains($title, 'summit')) {
            $score += 3;
        } elseif (preg_match('/\b(cairn|trig|top)\b/', $title)) {
            $score += 2;
        }

        if (str_contains($title, Str::lower($hill->name))) {
            $score += 3;
        }

        if (str_contains($tags, 'summit')) {
            $score += 2;
        }

        // "Geograph" is the site's own grade for a representative photo of the square.
        if (str_contains($tags, 'type:geograph')) {
            $score += 1;
        }

        // Views of somewhere else, and portraits, make poor photos of the hill.
        if (preg_match('/\bview(s)?\b|\blooking\b|\btowards\b/', $title)) {
            $score -= 2;
        }

        if (preg_match('/\b(me|us|selfie)\b/', $title)) {
            $score -= 3;
        }

        if ($distance !== null) {
            $score -= $distance / 250;
        }

        return $score;
    }

    /**
     * Distance from the photo's position to the hill's summit, if both are known.
     *
     * @param  array<string, mixed>  $item
     */
    private function distanceInMetres(array $item, Hill $hill): ?float
    {
        if (! is_numeric($item['lat'] ?? null) || ! is_numeric($item['long'] ?? null)
            || $hill->latitude === null || $hill->longitude === null) {
            return null;
        }

        $lat1 = deg2rad($hill->latitude);
        $lat2 = deg2rad((float) $item['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $item['long'] - $hill->longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6_371_000 * 2 * asin(min(1, sqrt($a)));
    }
}
