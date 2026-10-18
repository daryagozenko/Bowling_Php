<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Lane;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startTime = now()->addDay()->startOfHour();

        return [
            'user_id' => User::factory(),
            'lane_id' => Lane::factory(),
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(2),
            'total_price' => '3000.00',
            'status' => 'active',
        ];
    }
}
