<?php

namespace App\Services;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Dev-драйвер SMS: сообщение пишется в лог (код виден при ручной проверке).
 * Прод-провайдер — отдельная реализация контракта по config('services.sms.driver').
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS на {$phone}: {$message}");
    }
}
