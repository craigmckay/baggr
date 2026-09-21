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
        $name = fake()->unique()->words(2, true).' Beinn';
        $slug = Str::slug($name);

        return [
            'name' => ucfirst($name),
            'height_m' => fake()->numberBetween(914, 1345),
            'region' => fake()->randomElement([
                'Northwest Highlands',
                'West Highlands',
                'Cairngorms NP and Monadhliath',
                'Southeastern Highlands',
            ]),
            'walkhighlands_url' => "https://www.walkhighlands.co.uk/munros/{$slug}",
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands',
        ];
    }
}
