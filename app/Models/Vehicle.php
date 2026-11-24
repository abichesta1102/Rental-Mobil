<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'plate', 'brand', 'model', 'year', 'color', 'transmission', 'seats',
    'price_per_day', 'late_fee_per_hour', 'purchase_price', 'photo', 'status',
])]
class Vehicle extends Model
{
    use SoftDeletes;

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'price_per_day' => 'decimal:2',
            'late_fee_per_hour' => 'decimal:2',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Kendaraan yang siap disewa dan bebas booking pada rentang [start, end]. */
    public function scopeAvailableBetween(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->where('status', 'available')
            ->whereDoesntHave('bookings', function (Builder $q) use ($start, $end) {
                $q->blocking()->overlapping($start, $end);
            });
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? '/storage/'.$this->photo : null;
    }
}
