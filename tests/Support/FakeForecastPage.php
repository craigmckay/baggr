<?php

namespace Tests\Support;

/**
 * A forecast page laid out like an MWIS one, with made-up weather. No MWIS text
 * belongs in this repository (spec §4).
 */
class FakeForecastPage
{
    /**
     * @param  list<array{date: string, wind?: string, cloud_free?: string, rain?: string, headline?: string, freezing?: string}>  $days
     */
    public static function make(array $days): string
    {
        $row = fn (string $label, string $value) => "<div class=\"row\">\n<div class=\"col-12 col-lg-5\"><h4>{$label}</h4></div>\n<div class=\"col-12 col-lg-7\">{$value}</div>\n</div>\n";
        $blocks = '';
        $tabs = '';

        foreach ($days as $number => $day) {
            $tabs .= "<li><a id=\"ForecastTab{$number}\">Tab</a></li>";
            $blocks .= "<div id=\"Forecast{$number}\" class=\"forecast forecast-area tab-pane\">\n";
            $blocks .= "<h2 class=\"sr-only\">{$day['date']}</h2>\n";
            $blocks .= $row('Viewing Forecast For', '<p><small>Last updated Fri 27th Nov 26 at 2:30PM</small></p>');

            if (isset($day['headline'])) {
                $blocks .= $row('Headline for Somewhere', "<p>{$day['headline']}</p>");
            }

            $blocks .= $row('How windy? (On the Munros)', '<p>'.($day['wind'] ?? 'Westerly 10 to 20mph.').'</p>');
            $blocks .= $row('Effect of the wind on you?', '<p>Not much.</p>');
            $blocks .= $row('How Wet?', '<p><strong>'.($day['rain'] ?? 'Mostly dry').'</strong></p><p>A longer made-up paragraph.</p>');
            $blocks .= $row('Chance of cloud free Munros?', '<p>'.($day['cloud_free'] ?? '80%').'</p>');
            $blocks .= $row('Freezing Level', '<p>'.($day['freezing'] ?? 'Above the summits').'</p>');
            $blocks .= "</div>\n";
        }

        return "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><ul>{$tabs}</ul><div class=\"tab-content\">{$blocks}</div></body></html>";
    }
}
