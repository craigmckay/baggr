<?php

namespace App\Enums;

/**
 * The way a day's hills are taken (docs/SPEC.md §8.1).
 */
enum WalkDirection: string
{
    case Clockwise = 'clockwise';
    case Anticlockwise = 'anticlockwise';
    case NorthToSouth = 'north_south';
    case SouthToNorth = 'south_north';
    case EastToWest = 'east_west';
    case WestToEast = 'west_east';
    case OutAndBack = 'out_and_back';

    public function label(): string
    {
        return match ($this) {
            self::Clockwise => __('Clockwise'),
            self::Anticlockwise => __('Anticlockwise'),
            self::NorthToSouth => __('North to south'),
            self::SouthToNorth => __('South to north'),
            self::EastToWest => __('East to west'),
            self::WestToEast => __('West to east'),
            self::OutAndBack => __('Out and back'),
        };
    }
}
