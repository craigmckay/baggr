<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HillName extends Model
{
    public $timestamps = false;

    protected $fillable = ['hill_id', 'name', 'sort_order'];

    /**
     * @return BelongsTo<Hill, $this>
     */
    public function hill(): BelongsTo
    {
        return $this->belongsTo(Hill::class);
    }
}
