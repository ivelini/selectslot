<?php

namespace App\Console\Commands;

use App\Services\SlotGridGenerator;
use Illuminate\Console\Command;

class SlotsGenerate extends Command
{
    protected $signature = 'slots:generate';

    protected $description = 'Генерация сетки слотов по шаблону недели на горизонт записи (ADR 0001)';

    public function handle(SlotGridGenerator $generator): int
    {
        $generator->generate();

        $this->info('Сетка слотов обновлена.');

        return self::SUCCESS;
    }
}
