<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lane;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LaneService
{
    /**
     * @return Collection<int, Lane>
     */
    public function getAll(): Collection
    {
        return Lane::query()->orderBy('number')->get();
    }

    /**
     * @param  array{number: int, status: string, price_per_hour: string|float}  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): Lane
    {
        return Lane::query()->create([
            'number' => (int) $data['number'],
            'status' => $data['status'],
            'price_per_hour' => $data['price_per_hour'],
        ]);
    }

    /**
     * @param  array{number: int, status: string, price_per_hour: string|float}  $data
     *
     * @throws ValidationException
     */
    public function update(Lane $lane, array $data): Lane
    {
        $lane->update([
            'number' => (int) $data['number'],
            'status' => $data['status'],
            'price_per_hour' => $data['price_per_hour'],
        ]);

        return $lane->fresh();
    }

    /**
     * Удалить дорожку можно только если на неё нет ни одной брони.
     * Иначе нарушится foreign key (bookings.lane_id → lanes.id).
     *
     * @throws ValidationException
     */
    public function delete(Lane $lane): void
    {
        $hasBookings = Booking::query()
            ->where('lane_id', $lane->id)
            ->exists();

        if ($hasBookings) {
            throw ValidationException::withMessages([
                'lane' => 'Нельзя удалить дорожку, у которой есть брони (даже отменённые или прошедшие). ',
            ]);
        }

        $lane->delete();
    }

    /**
     * Перевести дорожку в статус "broken" (архив).
     * Используется, когда удалить нельзя (есть брони), но дорожка больше не нужна.
     *
     * @throws ValidationException
     */
    public function archive(Lane $lane): Lane
    {
        if ($lane->status === 'broken') {
            throw ValidationException::withMessages([
                'lane' => 'Дорожка уже в архиве.',
            ]);
        }

        $lane->update(['status' => 'broken']);

        return $lane->fresh();
    }
}