<?php

namespace App\Contracts;

/**
 * Отправка SMS. Провайдер в v1 не выбран (documentations/integrations/):
 * dev использует LogSmsSender; подключение провайдера — новая реализация
 * контракта без изменения кода (см. config('sms.provider')).
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
