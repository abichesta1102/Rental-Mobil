<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id', 'vehicle_id', 'booking_code', 'destination', 'delivery_address',
    'pickup_at', 'return_at', 'actual_returned_at', 'pickup_notified_at', 'duration_days',
    'price_per_day', 'total_price', 'dp_amount', 'late_fee_total', 'status',
])]
class Booking extends Model
{
    /** Persentase DP dari total sewa. Ubah di sini kalau aturan DP berbeda. */
    public const DP_PERCENT = 30;

    /** Booking yang belum bayar DP menahan kendaraan selama sekian jam. */
    public const PENDING_HOLD_HOURS = 24;

    /** Status yang menahan kendaraan (tidak bisa dipesan orang lain). */
    public const BLOCKING_STATUSES = ['booked', 'assigned', 'delivering', 'on_rent', 'returned'];

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'actual_returned_at' => 'datetime',
            'pickup_notified_at' => 'datetime',
            'price_per_day' => 'decimal:2',
            'total_price' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'late_fee_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(Damage::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(LocationLog::class);
    }

    /** Booking yang sedang menahan kendaraan. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', self::BLOCKING_STATUSES)
                ->orWhere(function (Builder $q) {
                    $q->where('status', 'pending_dp')
                        ->where('created_at', '>=', now()->subHours(self::PENDING_HOLD_HOURS));
                });
        });
    }

    /** Booking yang rentang waktunya tumpang tindih dengan rentang [start, end]. */
    public function scopeOverlapping(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->where('pickup_at', '<', $end)->where('return_at', '>', $start);
    }

    /** Jumlah hari sewa: dibulatkan ke atas per 24 jam, minimal 1 hari. */
    public static function calculateDays(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        return max(1, (int) ceil($seconds / 86400));
    }

    /**
     * Hitung durasi hari, kelebihan jam (terlambat dari siklus 24 jam), dan rincian harga sewa.
     * Aturan:
     * - Jika durasi <= 24 jam: dihitung 1 hari penuh, kelebihan jam = 0.
     * - Jika durasi > 24 jam:
     *   - Hari sewa pokok = floor(total_jam / 24)
     *   - Jam kelebihan = ceil(sisa menit / 60)
     *   - Biaya kelebihan = jam_kelebihan * denda_per_jam
     *   - Total sewa = (hari * tarif_harian) + biaya_kelebihan
     */
    public static function calculatePricing(DateTimeInterface $start, DateTimeInterface $end, float $pricePerDay, float $lateFeePerHour): array
    {
        $totalSeconds = max(0, $end->getTimestamp() - $start->getTimestamp());
        $totalMinutes = (int) ceil($totalSeconds / 60);
        $totalHours = $totalMinutes / 60;

        if ($totalHours <= 24) {
            $days = 1;
            $extraHours = 0;
            $basePrice = $pricePerDay;
            $overtimeFee = 0;
        } else {
            $days = (int) floor($totalHours / 24);
            $remainingMinutes = $totalMinutes - ($days * 24 * 60);
            $extraHours = (int) ceil($remainingMinutes / 60);

            if ($extraHours >= 24) {
                $days += 1;
                $extraHours = 0;
            }

            $basePrice = $days * $pricePerDay;
            $overtimeFee = $extraHours * $lateFeePerHour;
        }

        $total = $basePrice + $overtimeFee;
        $dp = self::calculateDp($total);

        return [
            'days' => $days,
            'extra_hours' => $extraHours,
            'base_price' => $basePrice,
            'overtime_fee' => $overtimeFee,
            'total' => $total,
            'dp' => $dp,
        ];
    }

    /** DP dibulatkan ke atas per Rp10.000. */
    public static function calculateDp(float $total): float
    {
        return ceil($total * self::DP_PERCENT / 100 / 10000) * 10000;
    }

    /** Kode booking, contoh: RNT-20261116-001. */
    public static function generateCode(): string
    {
        $prefix = 'RNT-'.now()->format('Ymd').'-';
        $latest = self::where('booking_code', 'like', $prefix.'%')
            ->orderByDesc('booking_code')
            ->value('booking_code');

        $nextNumber = 1;
        if ($latest) {
            $numPart = (int) substr($latest, strlen($prefix));
            $nextNumber = $numPart + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
