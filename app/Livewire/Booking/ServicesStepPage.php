<?php

namespace App\Livewire\Booking;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Шаг 2 «Услуги» (мокап booking/services.html). Заглушка: механика — отдельным планом.
 */
#[Layout('layouts.public')]
class ServicesStepPage extends Component
{
    public function render(): View
    {
        return view('livewire.booking.services-step-page');
    }
}
