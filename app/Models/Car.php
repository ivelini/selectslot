<?php

namespace App\Models;

use App\Enums\CarTypeEnum;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $plate
 * @property int|null $radius
 * @property CarTypeEnum|null $car_type
 * @property bool $has_runflat
 * @property bool $has_tpms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_id', 'plate', 'radius', 'car_type', 'has_runflat', 'has_tpms'])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    protected function casts(): array
    {
        return [
            'car_type' => CarTypeEnum::class,
            'has_runflat' => 'boolean',
            'has_tpms' => 'boolean',
        ];
    }
}
