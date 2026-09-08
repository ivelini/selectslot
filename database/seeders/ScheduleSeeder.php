<?php

namespace Database\Seeders;

use App\Models\ScheduleTemplate;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    /** Часы мастерской из сценария мокапа: Пн–Сб 9:00–19:00, Вс — выходной. */
    public function run(): void
    {
        foreach (range(0, 6) as $weekday) {
            ScheduleTemplate::updateOrCreate(
                ['weekday' => $weekday],
                $weekday === 6
                    ? ['open_time' => null, 'close_time' => null]
                    : ['open_time' => '09:00:00', 'close_time' => '19:00:00'],
            );
        }
    }
}
