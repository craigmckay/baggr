<?php

namespace App\Enums;

/**
 * How good a day looks for the hills (spec §4), from the wind and the chance of
 * cloud-free summits. Rain never changes it. The thresholds are in config/mwis.php.
 */
enum WeatherRating: string
{
    case Go = 'go';
    case Marginal = 'marginal';
    case Poor = 'poor';

    /** Null when either number couldn't be read from the forecast. */
    public static function fromNumbers(?int $windMph, ?int $cloudFreePercent): ?self
    {
        if ($windMph === null || $cloudFreePercent === null) {
            return null;
        }

        $rules = config('mwis.rating');

        return match (true) {
            $cloudFreePercent < $rules['poor_below_cloud_free'], $windMph > $rules['poor_above_wind'] => self::Poor,
            $windMph >= $rules['marginal_from_wind'] => self::Marginal,
            default => self::Go,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Go => __('Go'),
            self::Marginal => __('Marginal'),
            self::Poor => __('Poor'),
        };
    }

    /** The Flux badge colour. */
    public function colour(): string
    {
        return match ($this) {
            self::Go => 'green',
            self::Marginal => 'amber',
            self::Poor => 'red',
        };
    }

    /** Best first, for sorting hills by their weather. */
    public function order(): int
    {
        return match ($this) {
            self::Go => 0,
            self::Marginal => 1,
            self::Poor => 2,
        };
    }
}
