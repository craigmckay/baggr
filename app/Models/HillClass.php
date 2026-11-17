<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HillClass extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'show_in_list', 'sort_order'];

    protected function casts(): array
    {
        return ['show_in_list' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Hill, $this>
     */
    public function hills(): BelongsToMany
    {
        return $this->belongsToMany(Hill::class, 'class_hill');
    }
}
