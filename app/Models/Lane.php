<?php

namespace App\Models;

use Database\Factories\LaneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'status', 'price_per_hour'])]
class Lane extends Model
{
    /** @use HasFactory<LaneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
