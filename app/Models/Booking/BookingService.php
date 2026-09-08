<?php

namespace App\Models\Booking;

use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Строка состава записи с ценой на момент записи (снимок, корректируется оператором — ФТ-19).
 *
 * @property int $id
 * @property int $booking_id
 * @property int $service_id
 * @property int $price цена за единицу (снимок, ФТ-19)
 * @property int $quantity 1–4
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'service_id', 'price', 'quantity'])]
class BookingService extends Model
{
    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
