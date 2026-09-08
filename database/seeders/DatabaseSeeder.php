<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Порядок = граф зависимостей: справочники → шаблон → сетка слотов → демо-данные.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            ScheduleSeeder::class,
            SettingsSeeder::class,
            SlotSeeder::class,
            DemoBookingSeeder::class,
        ]);
    }
}
