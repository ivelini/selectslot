<?php

namespace Database\Seeders;

use App\Services\SlotGridGenerator;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        // ADR 0001: сетка генерируется тем же сервисом, что и планировщик (slots:generate)
        app(SlotGridGenerator::class)->generate();
    }
}
