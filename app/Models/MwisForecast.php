<?php

namespace App\Models;

use App\Enums\WeatherRating;
use App\Support\Mwis\ForecastPageParser;
use App\Support\MwisAreas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What Baggr keeps of one MWIS area's forecast for one day (spec §4): a few short
 * extracts. The full forecast is always linked to.
 *
 * @property string $area
 * @property Carbon $date
 * @property Carbon|null $issued_at
 * @property string|null $headline
 * @property string|null $wind
 * @property int|null $wind_mph
 * @property string|null $rain
 * @property string|null $cloud_free
 * @property int|null $cloud_free_percent
 * @property string|null $freezing_level
 */
class MwisForecast extends Model
{
    protected $fillable = [
        'area',
        'date',
        'issued_at',
        'headline',
        'wind',
        'wind_mph',
        'rain',
        'cloud_free',
        'cloud_free_percent',
        'freezing_level',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'issued_at' => 'datetime',
            'wind_mph' => 'integer',
            'cloud_free_percent' => 'integer',
        ];
    }

    /** "40 to 55 mph" from the wind text, or one speed if that's all it gives. */
    public function windRange(): ?string
    {
        $speeds = (new ForecastPageParser)->windSpeeds((string) $this->wind);

        return $speeds === [] ? null : implode(' to ', array_unique($speeds)).' mph';
    }

    /** Only the Scottish areas are rated: the thresholds are tuned for Munros. */
    public function isRatedArea(): bool
    {
        return str_contains(MwisAreas::find($this->area)['url'] ?? '', '/scottish/');
    }

    public function rating(): ?WeatherRating
    {
        return $this->isRatedArea() ? WeatherRating::fromNumbers($this->wind_mph, $this->cloud_free_percent) : null;
    }
}
