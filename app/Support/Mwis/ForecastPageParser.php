<?php

namespace App\Support\Mwis;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads an area's forecast page on mwis.org.uk. Each day is a block with the id
 * Forecast0, Forecast1 or Forecast2, holding a dated heading and then one row for
 * each field: a heading with the field's name, and its text beside it. Only the
 * first day of an issue has a headline.
 *
 * If the page stops looking like that, this throws, so the last good forecast is kept.
 */
class ForecastPageParser
{
    /**
     * @return list<array{date: string, issued_at: ?CarbonImmutable, headline: ?string, wind: string, wind_mph: ?int, rain: ?string, cloud_free: ?string, cloud_free_percent: ?int, freezing_level: ?string}>
     *
     * @throws UnreadableForecast
     */
    public function parse(string $html): array
    {
        $xpath = new DOMXPath($this->load($html));
        $blocks = $xpath->query('//div[starts-with(@id, "Forecast") and contains(concat(" ", normalize-space(@class), " "), " forecast-area ")]');
        $days = [];

        foreach ($blocks ?: [] as $block) {
            if (! $block instanceof DOMElement) {
                continue;
            }

            $fields = $this->fields($xpath, $block);
            $date = $this->date($this->text($this->first($xpath, './/h2', $block)));
            $wind = $this->text($fields['how windy'] ?? null);

            if ($date === null || $wind === null) {
                throw new UnreadableForecast('A forecast day has no date or no wind.');
            }

            $cloudFree = $this->text($fields['chance of cloud free'] ?? null);

            $days[] = [
                'date' => $date,
                'issued_at' => $this->issuedAt((string) $block->textContent),
                'headline' => $this->text($fields['headline for'] ?? null),
                'wind' => $wind,
                'wind_mph' => $this->windMph($wind),
                'rain' => $this->rain($xpath, $fields['how wet'] ?? null),
                'cloud_free' => $cloudFree,
                'cloud_free_percent' => $this->highestPercent($cloudFree),
                'freezing_level' => $this->text($fields['freezing level'] ?? null),
            ];
        }

        if ($days === []) {
            throw new UnreadableForecast('No forecast days were found on the page.');
        }

        return $days;
    }

    /**
     * The top of the main wind range: 55 for "40 to 55mph, occasionally 60mph".
     * Gusts mentioned afterwards are ignored. With no range, the first speed given.
     */
    public function windMph(string $wind): ?int
    {
        if (preg_match('/(\d{1,3})\s*(?:mph)?\s*(?:to|or|-|–)\s*(\d{1,3})\s*\+?\s*mph/iu', $wind, $range)) {
            return max((int) $range[1], (int) $range[2]);
        }

        return preg_match('/(\d{1,3})\s*\+?\s*mph/i', $wind, $single) ? (int) $single[1] : null;
    }

    /** The best figure given: 30 for "10% around Skye rising to 30% inland". */
    public function highestPercent(?string $text): ?int
    {
        if ($text === null || ! preg_match_all('/(\d{1,3})\s*%/', $text, $matches)) {
            return null;
        }

        return min(100, max(array_map('intval', $matches[1])));
    }

    private function load(string $html): DOMDocument
    {
        if (trim($html) === '') {
            throw new UnreadableForecast('The page was empty.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The declaration stops the page's UTF-8 being read as Latin-1.
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * The text beside each field heading in one day's block.
     *
     * @return array<string, DOMElement>
     */
    private function fields(DOMXPath $xpath, DOMElement $block): array
    {
        $wanted = ['headline for', 'how windy', 'how wet', 'chance of cloud free', 'freezing level'];
        $fields = [];

        foreach ($xpath->query('.//h4', $block) ?: [] as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $name = mb_strtolower((string) $this->text($heading));
            $value = $heading->parentNode instanceof DOMElement ? $heading->parentNode->nextElementSibling : null;

            foreach ($wanted as $field) {
                if ($value !== null && str_starts_with($name, $field)) {
                    $fields[$field] = $value;
                }
            }
        }

        return $fields;
    }

    /** MWIS's own one-line summary of the rain, not the paragraph under it. */
    private function rain(DOMXPath $xpath, ?DOMElement $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $summary = $this->text($this->first($xpath, './/strong', $value))
            ?? $this->text($this->first($xpath, './/p', $value))
            ?? $this->text($value);

        return $summary === null ? null : Str::limit($summary, 160);
    }

    private function first(DOMXPath $xpath, string $query, DOMElement $within): ?DOMNode
    {
        $nodes = $xpath->query($query, $within);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node : null;
    }

    private function text(?DOMNode $node): ?string
    {
        if ($node === null) {
            return null;
        }

        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $node->textContent));

        return $text === '' ? null : $text;
    }

    /** "Saturday 28 November 2026" as 2026-11-28. */
    private function date(?string $heading): ?string
    {
        if ($heading === null || ! preg_match('/(\d{1,2})(?:st|nd|rd|th)?\s+([A-Z][a-z]+)\s+(\d{4})/', $heading, $match)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!j F Y', "{$match[1]} {$match[2]} {$match[3]}");

        return $date?->toDateString();
    }

    /** "Last updated Fri 27th Nov 26 at 2:30PM", which is UK time. */
    private function issuedAt(string $text): ?CarbonImmutable
    {
        if (! preg_match('/Last updated\s+\w+\s+(\d{1,2})(?:st|nd|rd|th)?\s+(\w{3})\s+(\d{2})\s+at\s+(\d{1,2}:\d{2}\s*[AP]M)/i', $text, $match)) {
            return null;
        }

        $issued = CarbonImmutable::createFromFormat(
            '!j M y g:iA',
            "{$match[1]} {$match[2]} {$match[3]} ".str_replace(' ', '', $match[4]),
            config('mwis.timezone'),
        );

        return $issued?->utc();
    }
}
