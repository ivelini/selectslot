<?php

namespace App\ValueObjects;

/**
 * Контакты клиента из сессионного черновика (ADR 0006): данные для создания записи.
 * phone — канон «7XXXXXXXXXX»; plate необязателен.
 */
final class CustomerDraft
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
        public readonly ?string $plate = null,
    ) {}
}
