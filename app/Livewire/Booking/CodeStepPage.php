<?php

namespace App\Livewire\Booking;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Шаг 4 «Подтверждение» (мокап booking/code.html). Заглушка: механика — отдельным планом.
 */
#[Layout('layouts.public')]
class CodeStepPage extends Component
{
    public function render(): View
    {
        return view('livewire.booking.code-step-page');
    }
}
