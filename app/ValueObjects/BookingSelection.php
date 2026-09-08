<?php

namespace App\ValueObjects;

/**
 * Актуальный выбор клиента при подтверждении (ФТ-8): слот (date+hour) и состав
 * с количествами. Цена в записи не передаётся — пересчитывается сервером.
 */
final class BookingSelection
{
    /**
     * @param  array<int, int>  $quantities  service_id => количество 1–4
     */
    public function __construct(
        public readonly string $date,
        public readonly int $hour,
        public readonly VehicleParams $params,
        public readonly array $quantities,
    ) {}
}
