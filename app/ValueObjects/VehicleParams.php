<?php

namespace App\ValueObjects;

use App\Enums\CarTypeEnum;

/**
 * Параметры автомобиля клиента, от которых зависит цена (ФТ-2): радиус и тип.
 * DTO — только данные; на сайте оба обязательны (варианта «не знаю» нет);
 * RunFlat/TPMS — доп. работы, на подбор правила не влияют.
 */
final class VehicleParams
{
    public function __construct(
        public readonly int $radius,
        public readonly CarTypeEnum $carType,
    ) {}
}
