<?php

namespace App\Models;

use Database\Factories\HillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hill extends Model
{
    /** @use HasFactory<HillFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'dobih_number',
        'height_m',
        'region',
        'walkhighlands_url',
        'mwis_forecast_url',
        'grid_ref',
        'latitude',
        'longitude',
        'smc_section',
        'smc_section_description',
        'rhb_section',
        'rhb_section_description',
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
