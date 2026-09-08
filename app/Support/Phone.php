<?php

namespace App\Support;

/**
 * Нормализация телефона к канону «7XXXXXXXXXX» (11 цифр, РФ).
 * Принимается ввод с «+7»/«8», скобками, пробелами и дефисами;
 * невалидный ввод (не 11 цифр, не 7/8 в начале) → null.
 */
final class Phone
{
    private function __construct() {}

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 11) {
            return null;
        }

        return match ($digits[0]) {
            '7' => $digits,
            '8' => '7'.substr($digits, 1),
            default => null,
        };
    }
}
