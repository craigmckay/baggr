<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database of British and Irish Hills
    |--------------------------------------------------------------------------
    |
    | The hill data is licensed CC BY 4.0, so every page showing it carries a
    | credit. Update the version when a newer database is imported.
    |
    */

    'version' => '18.6',

    'url' => 'https://www.hill-bagging.co.uk/dobih',

    'licence_url' => 'https://creativecommons.org/licenses/by/4.0/',

    /*
    |--------------------------------------------------------------------------
    | Hill lists
    |--------------------------------------------------------------------------
    |
    | The lists Baggr tracks, each a DoBIH class code. A "Tops" list holds only
    | the subsidiary tops, never the main hills. The first one is the default.
    |
    */

    'lists' => [
        'M' => 'Munros',
        'MT' => 'Munro Tops',
        'C' => 'Corbetts',
        'CT' => 'Corbett Tops',
        'G' => 'Grahams',
        'GT' => 'Graham Tops',
        'D' => 'Donalds',
        'DT' => 'Donald Tops',
    ],

    'countries' => [
        'S' => 'Scotland',
        'E' => 'England',
        'W' => 'Wales',
        'I' => 'Ireland',
        'M' => 'Isle of Man',
        'C' => 'Channel Islands',
        'ES' => 'England and Scotland',
    ],

    /** What each type of DoBIH area is called, in the order they're shown. */
    'area_types' => [
        'region' => 'Region',
        'murray_section' => 'Murray section',
        'rhb_section' => 'RHB section',
        'island' => 'Island',
        'county' => 'County',
        'authority' => 'Council area',
        'historic_county' => 'Historic county',
        'london_borough' => 'London borough',
        'named_area' => 'Area',
        'other' => 'Other',
    ],

    'map_types' => [
        'OS25' => 'OS Explorer',
        'OS50' => 'OS Landranger',
        'OSi50' => 'OSi Discovery',
    ],

];
