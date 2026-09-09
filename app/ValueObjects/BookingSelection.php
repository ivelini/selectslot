<?php

namespace App\ValueObjects;

/**
 * Актуальный выбор при создании записи (ФТ-8): слот (date+hour) и состав
 * с количествами. Цена в записи не передаётся — пересчитывается сервером.
 */
final class BookingSelection
{
    /**
     * @param  array<int, int>  $quantities  service_id => количество 1–4
     * @param  bool  $closeSlot  закрыть часовой слот с привязкой к записи: бронь с сайта — всегда (ФТ-8/ФТ-16), запись из админки — по чекбоксу «Закрыть слот» (ФТ-18)
     */
    public function __construct(
        public readonly string $date,
        public readonly int $hour,
        public readonly VehicleParams $params,
        public readonly array $quantities,
        public readonly bool $closeSlot = false,
    ) {}
}
