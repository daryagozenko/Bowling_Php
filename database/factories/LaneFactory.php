<?php

namespace Database\Factories;

use App\Models\Lane;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lane>
 */
class LaneFactory extends Factory
{
    protected $model = Lane::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 9999),
            'status' => 'active',
            'price_per_hour' => '1500.00',
        ];
    }
}
