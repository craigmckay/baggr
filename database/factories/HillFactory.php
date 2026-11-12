<?php

namespace Database\Factories;

use App\Models\Hill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hill>
 */
class HillFactory extends Factory
{
    protected $model = Hill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word().' Beinn';
        $slug = Str::slug($name);

        return [
            'name' => ucfirst($name),
            'slug' => $slug,
            'dobih_number' => fake()->unique()->numberBetween(1, 5000),
            'height_m' => fake()->numberBetween(914, 1345),
            'region' => fake()->randomElement([
                'Northwest Highlands',
                'West Highlands',
                'Cairngorms NP and Monadhliath',
                'Southeastern Highlands',
            ]),
            'walkhighlands_url' => "https://www.walkhighlands.co.uk/munros/{$slug}",
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands',
            'grid_ref' => 'NN'.fake()->numberBetween(100, 999).fake()->numberBetween(100, 999),
            'latitude' => fake()->latitude(56, 58),
            'longitude' => fake()->longitude(-5.5, -3.5),
            'smc_section' => fake()->numberBetween(1, 17),
            'smc_section_description' => 'Firth of Clyde to Strath Tay',
            'rhb_section' => fake()->randomElement(['01A', '02B', '10C']),
            'rhb_section_description' => 'Loch Tay to Perth',
            'photo_url' => null,
            'photo_page_url' => null,
            'photo_title' => null,
            'photo_author' => null,
            'photo_geograph_id' => null,
        ];
    }

    /**
     * A hill with a Geograph photo and its attribution.
     */
    public function withPhoto(): static
    {
        return $this->state(function () {
            $id = fake()->unique()->numberBetween(1_000_000, 8_999_999);

            return [
                'photo_url' => "https://s0.geograph.org.uk/geophotos/00/00/00/{$id}_abcdef12.jpg",
                'photo_page_url' => "https://www.geograph.org.uk/photo/{$id}",
                'photo_title' => 'Summit cairn',
                'photo_author' => fake()->name(),
                'photo_geograph_id' => $id,
            ];
        });
    }
}
