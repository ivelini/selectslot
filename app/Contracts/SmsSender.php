<?php

namespace App\Contracts;

/**
 * Отправка SMS. Провайдер в v1 не выбран (documentations/integrations/):
 * реализация выбирается по config('services.sms.driver') — dev использует
 * LogSmsSender; подключение провайдера — новая реализация без изменения кода.
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
