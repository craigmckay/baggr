<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MWIS areas
    |--------------------------------------------------------------------------
    |
    | The ten Mountain Weather Information Service forecast areas, keyed by the
    | last part of the area's forecast URL and listed in the order they are
    | shown. "path" is the part of the URL before it. Only the five Scottish
    | areas get a Go / Marginal / Poor rating (spec §4).
    |
    */

    'base_url' => 'https://www.mwis.org.uk/forecasts',

    /*
    |--------------------------------------------------------------------------
    | Forecasts
    |--------------------------------------------------------------------------
    |
    | Off until MWIS gives permission to show extracts from their forecasts
    | (spec §4). While it is off Baggr fetches nothing from MWIS and shows only
    | areas and links to the forecasts.
    |
    | Forecasts are fetched twice a day, at "fetch_hours" UK time, through
    | Laravel's scheduler.
    |
    */

    'enabled' => (bool) env('MWIS_FORECASTS', false),

    // 07:00 catches an overnight change before people set off; 18:00 is after
    // MWIS's main afternoon issue.
    'fetch_hours' => [7, 18],

    'timezone' => 'Europe/London',

    // How Baggr names itself to MWIS, so they can tell who is reading.
    'user_agent' => env('MWIS_USER_AGENT', 'Mozilla/5.0 (compatible; Baggr/1.0; +https://baggr.co.uk)'),

    /*
    | The rating (Scottish areas only). Rain never changes it. Poor if the best
    | chance of cloud-free summits is under "poor_below_cloud_free" per cent or
    | the wind is over "poor_above_wind" mph; Marginal from "marginal_from_wind"
    | mph; otherwise Go.
    */

    'rating' => [
        'poor_below_cloud_free' => 40,
        'poor_above_wind' => 40,
        'marginal_from_wind' => 30,
    ],

    'areas' => [
        'the-northwest-highlands' => ['code' => 'NW', 'name' => 'Northwest Highlands', 'path' => 'scottish'],
        'west-highlands' => ['code' => 'WH', 'name' => 'West Highlands', 'path' => 'scottish'],
        'cairngorms-np-and-monadhliath' => ['code' => 'EH', 'name' => 'Cairngorms NP and Monadhliath', 'path' => 'scottish'],
        'southeastern-highlands' => ['code' => 'SH', 'name' => 'Southeastern Highlands', 'path' => 'scottish'],
        'southern-uplands' => ['code' => 'SU', 'name' => 'Southern Uplands', 'path' => 'scottish'],
        'lake-district' => ['code' => 'LD', 'name' => 'Lake District', 'path' => 'english-and-welsh'],
        'yorkshire-dales-and-north-pennines' => ['code' => 'YD', 'name' => 'Yorkshire Dales and North Pennines', 'path' => 'english-and-welsh'],
        'peak-district' => ['code' => 'PD', 'name' => 'Peak District', 'path' => 'english-and-welsh'],
        'snowdonia-national-park' => ['code' => 'SN', 'name' => 'Snowdonia National Park', 'path' => 'english-and-welsh'],
        'brecon-beacons' => ['code' => 'BB', 'name' => 'Brecon Beacons', 'path' => 'english-and-welsh'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapping DoBIH areas to MWIS areas
    |--------------------------------------------------------------------------
    |
    | A hill's MWIS area is found in this order: the area MWIS is already known
    | to forecast for it (the Munros), then its DoBIH named area, then its
    | Murray section. Named areas beat Murray sections because they follow the
    | hills more closely (the Lake District, the Welsh ranges). Anything not
    | listed has no MWIS area: Ireland, the Isle of Man, the Northern and
    | Western Isles, the north east of Scotland, and much of England.
    |
    | The boundaries come from MWIS's own area descriptions, so they are close,
    | not exact. Run `php artisan hills:map-mwis` after changing them.
    |
    */

    'named_areas' => [
        'NE01' => 'LD', 'NE02' => 'LD', 'NE03' => 'LD', 'NE04' => 'LD', 'NE05' => 'LD', 'NE06' => 'LD', 'NE07' => 'LD',
        'NE08' => 'SU',
        'NE09' => 'YD', 'NE10' => 'YD', 'NE11' => 'YD', 'NE12' => 'YD',
        'NE13P' => 'PD',
        'NW01' => 'SN', 'NW02' => 'SN', 'NW03' => 'SN', 'NW04' => 'SN', 'NW05' => 'SN',
        'NW06' => 'SN', 'NW07' => 'SN', 'NW08' => 'SN', 'NW09' => 'SN', 'NW10' => 'SN',
        'NW11P' => 'SN',
        'NW11E' => 'BB', 'NW11R' => 'BB', 'NW12' => 'BB', 'NW13' => 'BB',
    ],

    'murray_sections' => [
        // Southern and central Highlands
        'M01A' => 'SH', 'M01B' => 'SH', 'M01C' => 'WH', 'M01D' => 'WH', 'M01E' => 'WH',
        'M03A' => 'WH', 'M03B' => 'WH', 'M03C' => 'WH',
        'M04A' => 'WH',
        'M05A' => 'SH',
        'M06A' => 'SH',
        'M07B' => 'SH',
        // The Cairngorms, the Monadhliath and Lochaber's eastern side
        'M08A' => 'EH', 'M08B' => 'EH',
        'M09A' => 'EH', 'M09B' => 'EH', 'M09C' => 'WH',
        // North of Glen Shiel, the north and Skye, Rum, Eigg and Canna
        'M10A' => 'NW', 'M10B' => 'NW', 'M10C' => 'NW', 'M10D' => 'WH',
        'M11A' => 'NW', 'M11B' => 'NW',
        'M12A' => 'NW', 'M12B' => 'NW',
        'M13A' => 'NW', 'M13B' => 'NW',
        'M14A' => 'NW', 'M14B' => 'NW',
        'M15A' => 'NW', 'M15B' => 'NW',
        'M16A' => 'NW', 'M16B' => 'NW', 'M16C' => 'NW', 'M16D' => 'NW', 'M16E' => 'NW', 'M16F' => 'NW',
        'M17A' => 'NW', 'M17B' => 'NW', 'M17C' => 'NW', 'M17D' => 'NW',
        // West of Loch Linnhe: Mull, Ardnamurchan, Argyll, the islands, Arran
        'M17E' => 'WH', 'M17F' => 'WH',
        'M18A' => 'WH', 'M18B' => 'WH', 'M18C' => 'WH',
        'M19A' => 'WH', 'M19B' => 'WH', 'M19C' => 'WH',
        'M20A' => 'WH', 'M20B' => 'WH', 'M20C' => 'WH',
        // The Central Belt hills, including the Ochils (MWIS puts them in the Southeastern Highlands)
        'M26A' => 'SH', 'M26B' => 'SH',
        // South of the Central Belt, and the Cheviots
        'M27A' => 'SU', 'M27B' => 'SU', 'M27C' => 'SU',
        'M28A' => 'SU', 'M28B' => 'SU',
        'M33' => 'SU',
        // Wales: north of Pumlumon is Snowdonia, the south is the Brecon Beacons
        'M30A' => 'SN', 'M30B' => 'SN', 'M30C' => 'SN', 'M30D' => 'SN', 'M30E' => 'SN', 'M30F' => 'SN',
        'M31A' => 'SN',
        'M31B' => 'BB', 'M31C' => 'BB',
        'M32A' => 'BB', 'M32B' => 'BB', 'M32C' => 'BB',
        // England
        'M34A' => 'LD', 'M34B' => 'LD', 'M34C' => 'LD',
        'M35A' => 'YD', 'M35B' => 'YD',
    ],

    /*
    | Murray sections that straddle two MWIS areas. A hill here takes the area of
    | the nearest Munro, because the Munros' areas are known.
    */

    'nearest_munro_sections' => ['M02A', 'M02B', 'M04B', 'M05B', 'M06B', 'M07A'],

    /*
    |--------------------------------------------------------------------------
    | Hills MWIS doesn't claim
    |--------------------------------------------------------------------------
    |
    | A hill can be given an MWIS area that MWIS's own description of the area
    | doesn't take in, such as the Lomonds in Fife. Baggr labels that the
    | "nearest area". A hill in one of these Murray sub-sections is labelled,
    | unless it has its own MWIS link, is in one of the named areas above, or is
    | in one of the hill ranges or hill sections below.
    |
    */

    'nearest_area_sections' => [
        'M17D', 'M17F',                 // Canna, Rum and Eigg; Coll and Tiree
        'M20A', 'M20B',                 // Jura and Colonsay; Islay
        'M26A', 'M26B',                 // The Central Belt, other than the Ochils and Sidlaws
        'M27A',                         // Ayr to the River Clyde
        'M34A', 'M34B', 'M34C',         // Round the Lake District
        'M35A', 'M35B',                 // Round the Dales and North Pennines
    ],

    // Glen Artney, the Ochils and the Southern Uplands ranges, which MWIS's descriptions take in.
    'claimed_hill_ranges' => ['D001', 'D002', 'D003', 'D004', 'D005', 'D006', 'D007', 'D008', 'D009', 'D010', 'D011', 'D012'],

    // The Sidlaws, which MWIS's "Angus hills" take in.
    'claimed_rhb_sections' => ['R20I'],

];
