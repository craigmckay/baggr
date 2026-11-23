<?php

namespace App\Models;

use App\Support\MwisAreas;
use Database\Factories\HillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'mwis_area',
        'grid_ref',
        'latitude',
        'longitude',
        'smc_section',
        'smc_section_description',
        'rhb_section',
        'rhb_section_description',
        'photo_url',
        'photo_page_url',
        'photo_title',
        'photo_author',
        'photo_geograph_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'metres' => 'float',
            'drop_m' => 'float',
            'col_height_m' => 'float',
            'revised_on' => 'date',
            'tump_only' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The MWIS forecast area the hill belongs to, or null if MWIS covers none
     * that it sits in. It is the mapped area, or for a Munro the area its stored
     * forecast link points at.
     *
     * @return array{slug: string, code: string, name: string, url: string, order: int}|null
     */
    public function mwisArea(): ?array
    {
        return MwisAreas::find($this->mwis_area) ?? MwisAreas::fromUrl($this->mwis_forecast_url);
    }

    /**
     * @return HasMany<Ascent, $this>
     */
    public function ascents(): HasMany
    {
        return $this->hasMany(Ascent::class);
    }

    /**
     * @return HasMany<HillName, $this>
     */
    public function alternateNames(): HasMany
    {
        return $this->hasMany(HillName::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Area, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_hill')->withPivot(['is_alt', 'is_county_top']);
    }

    /**
     * @return BelongsToMany<HillClass, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(HillClass::class, 'class_hill');
    }

    /**
     * @return BelongsToMany<OsMap, $this>
     */
    public function maps(): BelongsToMany
    {
        return $this->belongsToMany(OsMap::class, 'hill_map');
    }
}
