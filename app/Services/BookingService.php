<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lane;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private const BILLING_INCREMENT_MINUTES = 20;

    private const MAX_PRICE_CENTS = 9_999_999_999;

    /**
     * Создать бронь.
     *
     * @return Booking
     * @throws ValidationException
     */
    public function create(
        int $userId,
        int $laneId,
        CarbonImmutable $startTime,
        CarbonImmutable $endTime,
    ): Booking {
        [$adjustedEndTime, $billableMinutes] = $this->calculateBillable($startTime, $endTime);

        return DB::transaction(function () use (
            $userId, $laneId, $startTime, $adjustedEndTime, $billableMinutes
        ): Booking {
            $lane = $this->findLaneOrFail($laneId);
            $this->assertLaneIsActive($lane);
            $this->assertNoOverlap($lane->id, $startTime, $adjustedEndTime);

            $totalPrice = $this->calculateTotalPrice($lane, $billableMinutes);

            return Booking::query()->create([
                'user_id' => $userId,
                'lane_id' => $lane->id,
                'start_time' => $startTime,
                'end_time' => $adjustedEndTime,
                'total_price' => $totalPrice,
                'status' => 'active',
            ]);
        });
    }

    /**
     * Отменить бронь.
     *
     * @throws ValidationException
     */
    public function cancel(int $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId): Booking {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Отменить можно только активную бронь.',
                ]);
            }

            $booking->update(['status' => 'cancelled']);

            return $booking;
        });
    }

    /**
     * Завершить бронь (обычно по cron после end_time).
     *
     * @throws ValidationException
     */
    public function complete(int $bookingId): Booking
    {
        $booking = Booking::query()->whereKey($bookingId)->firstOrFail();

        if ($booking->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Завершить можно только активную бронь.',
            ]);
        }

        $booking->update(['status' => 'completed']);

        return $booking;
    }

    /**
     * Вернуть дорожки, свободные в диапазоне [from, to].
     *
     * @return Collection<int, Lane>
     */
    public function getAvailableLanes(
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): Collection {
        $busyLaneIds = Booking::query()
            ->where('status', 'active')
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from)
            ->pluck('lane_id')
            ->unique();

        return Lane::query()
            ->where('status', 'active')
            ->whereNotIn('id', $busyLaneIds)
            ->orderBy('number')
            ->get();
    }

    /**
     * Посчитать предварительную стоимость без создания брони.
     *
     * @return array{billable_minutes: int, total_price: string}
     */
    public function calculatePrice(
        int $laneId,
        CarbonImmutable $startTime,
        CarbonImmutable $endTime,
    ): array {
        [$adjustedEndTime, $billableMinutes] = $this->calculateBillable($startTime, $endTime);

        $lane = $this->findLaneOrFail($laneId);
        $this->assertLaneIsActive($lane);

        return [
            'billable_minutes' => $billableMinutes,
            'total_price' => $this->calculateTotalPrice($lane, $billableMinutes),
        ];
    }

    /**
     * Перенести активную бронь на новое время.
     *
     * Внутри: отменяет старую бронь и создаёт новую с тем же user_id и lane_id,
     * но с новыми start_time/end_time. Всё в одной транзакции — если новая
     * не создастся, старая останется активной.
     *
     * @throws ValidationException
     */
    public function reschedule(
        int $bookingId,
        CarbonImmutable $newStartTime,
        CarbonImmutable $newEndTime,
    ): Booking {
        return DB::transaction(function () use ($bookingId, $newStartTime, $newEndTime): Booking {
            $oldBooking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if ($oldBooking === null) {
                throw ValidationException::withMessages([
                    'booking' => 'Бронь не найдена.',
                ]);
            }

            if ($oldBooking->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Перенести можно только активную бронь.',
                ]);
            }

            $oldBooking->update(['status' => 'cancelled']);

            [$adjustedEndTime, $billableMinutes] = $this->calculateBillable($newStartTime, $newEndTime);

            $lane = $this->findLaneOrFail($oldBooking->lane_id);
            $this->assertLaneIsActive($lane);
            $this->assertNoOverlap($lane->id, $newStartTime, $adjustedEndTime);

            return Booking::query()->create([
                'user_id' => $oldBooking->user_id,
                'lane_id' => $lane->id,
                'start_time' => $newStartTime,
                'end_time' => $adjustedEndTime,
                'total_price' => $this->calculateTotalPrice($lane, $billableMinutes),
                'status' => 'active',
            ]);
        });
}

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Округлить длительность вверх до 20 минут и вернуть скорректированный end_time.
     *
     * @return array{0: CarbonImmutable, 1: int} [adjustedEndTime, billableMinutes]
     * @throws ValidationException
     */
    private function calculateBillable(
        CarbonImmutable $startTime,
        CarbonImmutable $endTime,
    ): array {
        $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();

        if ($durationSeconds <= 0) {
            throw ValidationException::withMessages([
                'end_time' => 'Время окончания должно быть позже времени начала.',
            ]);
        }

        $incrementSeconds = self::BILLING_INCREMENT_MINUTES * 60;
        $billableIncrements = (int) ceil($durationSeconds / $incrementSeconds);
        $billableMinutes = $billableIncrements * self::BILLING_INCREMENT_MINUTES;

        $adjustedEndTime = $startTime->addMinutes($billableMinutes);

        return [$adjustedEndTime, $billableMinutes];
    }

    /**
     * @throws ValidationException
     */
    private function findLaneOrFail(int $laneId): Lane
    {
        $lane = Lane::query()->whereKey($laneId)->first();

        if ($lane === null) {
            throw ValidationException::withMessages([
                'lane_id' => 'Дорожка не найдена.',
            ]);
        }

        return $lane;
    }

    /**
     * @throws ValidationException
     */
    private function assertLaneIsActive(Lane $lane): void
    {
        if ($lane->status !== 'active') {
            throw ValidationException::withMessages([
                'lane_id' => 'Эта дорожка сейчас недоступна для бронирования.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertNoOverlap(
        int $laneId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $excludeBookingId = null,
    ): void {
        $query = Booking::query()
            ->where('lane_id', $laneId)
            ->where('status', 'active')
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_time' => 'На это время дорожка уже забронирована.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function calculateTotalPrice(Lane $lane, int $billableMinutes): string
    {
        // Цена за час в копейках — так избегаем потерь float при умножении.
        $pricePerHourCents = (int) round((float) $lane->price_per_hour * 100);

        // Цена за billableMinutes в копейках, с округлением к ближайшей копейке.
        $totalPriceCents = (int) round($pricePerHourCents * $billableMinutes / 60);

        if ($totalPriceCents > self::MAX_PRICE_CENTS) {
            throw ValidationException::withMessages([
                'end_time' => 'Стоимость бронирования превышает допустимое значение.',
            ]);
        }

        return intdiv($totalPriceCents, 100).'.'
            .str_pad((string) ($totalPriceCents % 100), 2, '0', STR_PAD_LEFT);
    }
}