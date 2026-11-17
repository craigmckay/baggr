<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OsMap extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'ref', 'name'];

    /**
     * @return BelongsToMany<Hill, $this>
     */
    public function hills(): BelongsToMany
    {
        return $this->belongsToMany(Hill::class, 'hill_map');
    }
}
