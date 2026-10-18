<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lane;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private const BILLING_INCREMENT_MINUTES = 20;

    private const MAX_PRICE_CENTS = 9_999_999_999;

    public function create(
        int $userId,
        int $laneId,
        CarbonImmutable $startTime,
        CarbonImmutable $endTime,
    ): Booking {
        $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();

        if ($durationSeconds <= 0) {
            throw ValidationException::withMessages([
                'end_time' => 'Время окончания должно быть позже времени начала.',
            ]);
        }

        $incrementSeconds = self::BILLING_INCREMENT_MINUTES * 60;
        $billableIncrements = intdiv($durationSeconds + $incrementSeconds - 1, $incrementSeconds);
        $billableMinutes = $billableIncrements * self::BILLING_INCREMENT_MINUTES;

        return DB::transaction(function () use (
            $userId,
            $laneId,
            $startTime,
            $endTime,
            $billableMinutes,
        ): Booking {
            $lane = Lane::query()
                ->whereKey($laneId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lane->status !== 'active') {
                throw ValidationException::withMessages([
                    'lane_id' => 'Эта дорожка сейчас недоступна для бронирования.',
                ]);
            }

            $hasOverlap = Booking::query()
                ->where('lane_id', $lane->id)
                ->where('status', 'active')
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'start_time' => 'На это время дорожка уже забронирована.',
                ]);
            }

            $pricePerHourCents = (int) round((float) $lane->price_per_hour * 100);
            $maximumHourlyPriceCents = intdiv(
                (self::MAX_PRICE_CENTS * 60) + 29,
                $billableMinutes,
            );

            if ($pricePerHourCents > $maximumHourlyPriceCents) {
                throw ValidationException::withMessages([
                    'end_time' => 'Стоимость бронирования превышает допустимое значение.',
                ]);
            }

            $totalPriceCents = intdiv(
                ($pricePerHourCents * $billableMinutes) + 30,
                60,
            );
            $totalPrice = intdiv($totalPriceCents, 100).'.'.
                str_pad((string) ($totalPriceCents % 100), 2, '0', STR_PAD_LEFT);

            return Booking::query()->create([
                'user_id' => $userId,
                'lane_id' => $lane->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'total_price' => $totalPrice,
                'status' => 'active',
            ]);
        });
    }
}
