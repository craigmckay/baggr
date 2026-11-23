<?php

namespace App\Console\Commands;

use App\Support\MwisAreaMapper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hills:map-mwis')]
#[Description('Work out the MWIS forecast area of every hill from its DoBIH areas (see config/mwis.php)')]
class MapMwisAreas extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MwisAreaMapper $mapper): int
    {
        $result = $mapper->run();

        $this->info("{$result['mapped']} hills have an MWIS area ({$result['nearest']} of them from the nearest Munro). {$result['none']} have none.");

        return self::SUCCESS;
    }
}
