<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'height_m',
        'region',
        'walkhighlands_url',
        'mwis_forecast_url',
    ];
}
