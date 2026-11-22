<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MWIS areas
    |--------------------------------------------------------------------------
    |
    | The Mountain Weather Information Service forecast areas Baggr knows
    | about, keyed by the last part of the area's forecast URL. A hill's area is
    | read from its forecast URL, so only hills that have one (the Munros so
    | far) have an area. Listed in the order they are shown.
    |
    */

    'areas' => [
        'the-northwest-highlands' => ['code' => 'NW', 'name' => 'Northwest Highlands'],
        'west-highlands' => ['code' => 'WH', 'name' => 'West Highlands'],
        'cairngorms-np-and-monadhliath' => ['code' => 'EH', 'name' => 'Cairngorms NP and Monadhliath'],
        'southeastern-highlands' => ['code' => 'SH', 'name' => 'Southeastern Highlands'],
    ],

];
