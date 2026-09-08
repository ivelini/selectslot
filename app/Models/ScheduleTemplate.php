<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Шаблон недели: для каждого дня часы работы (0 (пн) – 6 (вс)); open+close = null → выходной.
 *
 * @property int $id
 * @property int $weekday
 * @property string|null $open_time
 * @property string|null $close_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['weekday', 'open_time', 'close_time'])]
class ScheduleTemplate extends Model {}
