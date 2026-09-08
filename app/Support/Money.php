<?php

namespace App\Support;

/**
 * Форматирование денег в рубли для показа («1 700 ₽», «1 700,50 ₽»).
 * Цены в БД — копейки (unsignedInteger); форматирование — только отображение.
 */
final class Money
{
    private function __construct() {}

    public static function format(int $kopecks): string
    {
        $kopecks = max(0, $kopecks);
        $rubles = intdiv($kopecks, 100);
        $remainder = $kopecks % 100;

        $amount = $remainder === 0
            ? number_format($rubles, 0, ',', ' ')
            : number_format($kopecks / 100, 2, ',', ' ');

        return $amount.' ₽';
    }
}
