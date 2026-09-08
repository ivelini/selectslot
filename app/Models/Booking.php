<?php

namespace App\Models;

use App\Enums\BookingSourceEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\CarTypeEnum;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Запись на конкретное время начала; параметры авто и цена — снимок на момент создания (ADR 0004).
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $car_id
 * @property int $slot_id
 * @property string $start_time
 * @property BookingStatusEnum $status
 * @property BookingSourceEnum $source
 * @property string|null $cancel_reason
 * @property string|null $confirmation_code_hash
 * @property string|null $idempotency_key
 * @property int $radius
 * @property CarTypeEnum $car_type
 * @property bool $has_runflat
 * @property bool $has_tpms
 * @property int $total_price
 * @property int|null $operator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'customer_id', 'car_id', 'slot_id', 'start_time', 'status', 'source',
    'cancel_reason', 'confirmation_code_hash', 'idempotency_key',
    'radius', 'car_type', 'has_runflat', 'has_tpms', 'total_price', 'operator_id',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /** @return BelongsTo<Slot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /** @return BelongsTo<User, $this> создатель записи из админки (nullable) */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return HasMany<BookingService, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    /** @return HasOne<Slot, $this> слот, закрытый привязкой к этой записи (чекбокс, ФТ-16) */
    public function closedSlot(): HasOne
    {
        return $this->hasOne(Slot::class, 'booking_id');
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatusEnum::class,
            'source' => BookingSourceEnum::class,
            'car_type' => CarTypeEnum::class,
            'has_runflat' => 'boolean',
            'has_tpms' => 'boolean',
        ];
    }
}
