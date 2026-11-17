<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Area extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'short_name', 'name', 'type', 'country', 'mwis_area'];

    /**
     * @return BelongsToMany<Hill, $this>
     */
    public function hills(): BelongsToMany
    {
        return $this->belongsToMany(Hill::class, 'area_hill')->withPivot(['is_alt', 'is_county_top']);
    }
}
