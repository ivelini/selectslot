<?php

namespace App\Enums;

/**
 * Радиусы колёс, предлагаемые клиенту на сайте (R13–R21).
 * В БД (price_rules, bookings) хранится int-значение радиуса.
 * R22 в прайсе нет (группы до R21) — R22 не предлагается.
 */
enum WheelRadiusEnum: int
{
    case R13 = 13;
    case R14 = 14;
    case R15 = 15;
    case R16 = 16;
    case R17 = 17;
    case R18 = 18;
    case R19 = 19;
    case R20 = 20;
    case R21 = 21;

    public function label(): string
    {
        return 'R'.$this->value;
    }
}
