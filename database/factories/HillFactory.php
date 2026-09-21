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
        ];
    }
}
