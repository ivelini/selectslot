<?php

namespace App\Support;

use App\Enums\CarTypeEnum;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Контракт query-параметров шага записи (ADR 0006): выбор живёт в URL.
 * Парсинг/нормализация входных значений, общих для шагов 1–4.
 *
 * Время начала — целый час «HH:00» (сайт даёт целые часы); количество услуги —
 * 1–4 (по умолчанию 4 — сезонный комплект, ФТ-4).
 */
final class BookingQuery
{
    /** Количество услуги: сезонный комплект 4 колеса (ФТ-4). */
    public const DEFAULT_QUANTITY = 4;

    public const MIN_QUANTITY = 1;

    public const MAX_QUANTITY = 4;

    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):00$/';

    private function __construct() {}

    /** Строгое чтение «Y-m-d»: мусор и переполнение дат (9999-99-99) отсекаются round-trip'ом. */
    public static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match(self::DATE_PATTERN, $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Час «HH:00» из query: невалидное значение → null. */
    public static function parseTimeHour(?string $value): ?int
    {
        if ($value === null || preg_match(self::TIME_PATTERN, $value) !== 1) {
            return null;
        }

        return (int) substr($value, 0, 2);
    }

    /**
     * Количества выбранных услуг: отсутствующее → DEFAULT_QUANTITY, значения
     * вне [MIN_QUANTITY..MAX_QUANTITY] поджимаются к границе.
     *
     * @param  list<int>  $serviceIds
     * @param  array<int, int>  $quantities
     * @return array<int, int>
     */
    public static function normalizeQuantities(array $serviceIds, array $quantities): array
    {
        $normalized = [];
        foreach ($serviceIds as $serviceId) {
            $raw = $quantities[$serviceId] ?? self::DEFAULT_QUANTITY;
            $normalized[$serviceId] = max(self::MIN_QUANTITY, min(self::MAX_QUANTITY, (int) $raw));
        }

        return $normalized;
    }

    /** Тип авто из query: не bookable-тип (грузовик — по звонку, ФТ-4) → null. */
    public static function carTypeValueOrNull(?string $value): ?string
    {
        $carType = CarTypeEnum::tryFrom((string) $value);

        return $carType !== null && in_array($carType, CarTypeEnum::bookable(), true) ? $carType->value : null;
    }
}
