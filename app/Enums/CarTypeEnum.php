<?php

namespace App\Enums;

enum CarTypeEnum: string
{
    case Passenger = 'passenger';
    case Crossover = 'crossover';
    case Suv = 'suv';
    case Truck = 'truck';

    public function label(): string
    {
        return match ($this) {
            self::Passenger => 'Легковая',
            self::Crossover => 'Кроссовер',
            self::Suv => 'Внедорожник',
            self::Truck => 'Грузовик',
        };
    }
}
