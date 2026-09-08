<?php

namespace App\ValueObjects;

use App\Enums\CodeStatusEnum;
use App\Models\Booking\BookingCode;

/**
 * Результат проверки кода (только данные): статус и найденная строка кода
 * (для Valid/Used/Expired). BookingCode при Invalid — null.
 */
final class CodeVerification
{
    public function __construct(
        public readonly CodeStatusEnum $status,
        public readonly ?BookingCode $code = null,
    ) {}
}
