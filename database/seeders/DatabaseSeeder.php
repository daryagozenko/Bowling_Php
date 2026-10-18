<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Lane;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Lane::factory(6)->create()->each(function (Lane $lane) use ($user): void {
            Booking::factory()->create([
                'user_id' => $user->id,
                'lane_id' => $lane->id,
            ]);
        });
    }
}
