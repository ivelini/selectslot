<?php

namespace App\Console\Commands;

use App\Actions\GenerateSlotGridAction;
use Illuminate\Console\Command;

class SlotsGenerate extends Command
{
    protected $signature = 'slots:generate';

    protected $description = 'Генерация сетки слотов по шаблону недели на горизонт записи (ADR 0001)';

    public function handle(GenerateSlotGridAction $generateSlots): int
    {
        $generateSlots->handle();

        $this->info('Сетка слотов обновлена.');

        return self::SUCCESS;
    }
}
