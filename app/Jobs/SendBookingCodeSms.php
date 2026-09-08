<?php

namespace App\Jobs;

use App\Contracts\SmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Доставка SMS с кодом подтверждения (ФТ-23/ФТ-24): через очередь с ретраями —
 * сбой провайдера не блокирует запрос кода и подтверждение брони.
 */
class SendBookingCodeSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(
        public string $phone,
        public string $code,
    ) {}

    public function handle(SmsSender $sender): void
    {
        $sender->send($this->phone, "Код подтверждения записи: {$this->code}");
    }
}
