<?php

namespace App\Enums;

enum BookingStatusEnum: string
{
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case Done = 'done';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Подтверждена',
            self::Arrived => 'Клиент приехал',
            self::Done => 'Завершена',
            self::Cancelled => 'Отменена',
            self::NoShow => 'Неявка',
        };
    }
}
