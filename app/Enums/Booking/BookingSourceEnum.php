<?php

namespace App\Enums\Booking;

enum BookingSourceEnum: string
{
    case Site = 'site';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Сайт',
            self::Admin => 'Админка',
        };
    }
}
