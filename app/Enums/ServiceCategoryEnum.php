<?php

namespace App\Enums;

enum ServiceCategoryEnum: string
{
    case Tire = 'tire';
    case Storage = 'storage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tire => 'Шиномонтаж',
            self::Storage => 'Хранение',
            self::Other => 'Прочее',
        };
    }
}
