<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Слот — часовое окно (date + hour), единица группировки записей и закрытия времени.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $hour
 * @property bool $is_closed
 * @property string|null $close_reason
 * @property int|null $booking_id привязка закрытия к записи (ФТ-16)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'hour', 'is_closed', 'close_reason', 'booking_id'])]
class Slot extends Model
{
    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return BelongsTo<Booking, $this> запись, чьё закрытие висит на слоте (nullable) */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}
