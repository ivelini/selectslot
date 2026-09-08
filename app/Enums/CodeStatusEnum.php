<?php

namespace App\Enums;

/**
 * Результат проверки кода подтверждения (BookingCodeService::verify):
 * Valid — активный код можно подтверждать; Used — код уже создал запись
 * (повторный submit возвращает существующую запись, НФ-1); Expired — истёк
 * срок действия (TTL); Invalid — кода для телефона нет.
 */
enum CodeStatusEnum
{
    case Valid;

    case Used;

    case Expired;

    case Invalid;
}
