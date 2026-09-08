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

    /**
     * Типы, которые клиент выбирает на сайте. Грузовик — вне правил прайса:
     * грузовые авто записываются по звонку (ФТ-18).
     *
     * @return list<self>
     */
    public static function bookable(): array
    {
        return [self::Passenger, self::Crossover, self::Suv];
    }
}
