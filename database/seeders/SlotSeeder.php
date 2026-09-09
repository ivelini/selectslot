<?php

namespace Database\Seeders;

use App\Actions\GenerateSlotGridAction;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        // ADR 0001: сетка генерируется тем же действием, что и планировщик (slots:generate)
        app(GenerateSlotGridAction::class)->handle();
    }
}
