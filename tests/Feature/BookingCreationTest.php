<?php

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Lane;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('rounds booking duration up to the next twenty-minute increment', function (
    int $durationMinutes,
    string $expectedPrice,
) {
    $user = User::factory()->create();
    $lane = Lane::factory()->create(['price_per_hour' => '1500.00']);
    $startTime = CarbonImmutable::parse('2026-10-19 12:00:00');

    $booking = app(BookingService::class)->create(
        $user->id,
        $lane->id,
        $startTime,
        $startTime->addMinutes($durationMinutes),
    );

    expect($booking->total_price)->toBe($expectedPrice);
})->with([
    'two hours remain two hours' => [120, '3000.00'],
    'one hour and thirty-seven minutes bill as one hour and forty minutes' => [97, '2500.00'],
    'fifty-one minutes bill as one hour' => [51, '1500.00'],
]);

it('rejects overlapping active bookings on the same lane', function () {
    $user = User::factory()->create();
    $lane = Lane::factory()->create();
    $startTime = CarbonImmutable::parse('2026-10-19 12:00:00');

    Booking::factory()->create([
        'user_id' => $user->id,
        'lane_id' => $lane->id,
        'start_time' => $startTime,
        'end_time' => $startTime->addHours(2),
    ]);

    expect(fn () => app(BookingService::class)->create(
        $user->id,
        $lane->id,
        $startTime->addMinutes(30),
        $startTime->addHours(3),
    ))->toThrow(ValidationException::class);
});

it('allows a booking to start when the previous booking ends', function () {
    $user = User::factory()->create();
    $lane = Lane::factory()->create();
    $startTime = CarbonImmutable::parse('2026-10-19 12:00:00');

    Booking::factory()->create([
        'user_id' => $user->id,
        'lane_id' => $lane->id,
        'start_time' => $startTime,
        'end_time' => $startTime->addHours(2),
    ]);

    $booking = app(BookingService::class)->create(
        $user->id,
        $lane->id,
        $startTime->addHours(2),
        $startTime->addHours(4),
    );

    expect($booking->exists)->toBeTrue();
});

it('rejects bookings on lanes that are not active', function () {
    $user = User::factory()->create();
    $lane = Lane::factory()->create(['status' => 'maintenance']);
    $startTime = CarbonImmutable::parse('2026-10-19 12:00:00');

    expect(fn () => app(BookingService::class)->create(
        $user->id,
        $lane->id,
        $startTime,
        $startTime->addHours(2),
    ))->toThrow(ValidationException::class);
});

it('validates the supplied user, lane, and booking time range', function () {
    $user = User::factory()->create();
    $lane = Lane::factory()->create();

    $validator = Validator::make([
        'user_id' => $user->id,
        'lane_id' => $lane->id,
        'start_time' => '2026-10-19 12:00:00',
        'end_time' => '2026-10-19 11:00:00',
    ], (new StoreBookingRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_time'))->toBeTrue();
});

it('rejects unknown user and lane identifiers', function () {
    $startTime = '2026-10-19 12:00:00';

    $validator = Validator::make([
        'user_id' => 999,
        'lane_id' => 999,
        'start_time' => $startTime,
        'end_time' => '2026-10-19 14:00:00',
    ], (new StoreBookingRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('user_id'))->toBeTrue()
        ->and($validator->errors()->has('lane_id'))->toBeTrue();
});

it('allows clients to book for themselves and admins to select a user', function () {
    $client = User::factory()->create(['role' => 'client']);
    $otherUser = User::factory()->create();
    $request = new StoreBookingRequest;
    $request->setUserResolver(fn () => $client);
    $request->merge(['user_id' => $otherUser->id]);

    expect($request->authorize())->toBeFalse();

    $request->merge(['user_id' => $client->id]);
    expect($request->authorize())->toBeTrue();

    $admin = User::factory()->create(['role' => 'admin']);
    $request->setUserResolver(fn () => $admin);
    $request->merge(['user_id' => $otherUser->id]);

    expect($request->authorize())->toBeTrue();
});

it('seeds a demo user, lanes, and bookings', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(Lane::query()->count())->toBe(6)
        ->and(Booking::query()->count())->toBe(6);
});
