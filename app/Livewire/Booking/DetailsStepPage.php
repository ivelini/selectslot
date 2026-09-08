<?php

namespace App\Livewire\Booking;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Шаг 3 «Данные» (мокап booking/details.html). Заглушка: механика — отдельным планом.
 */
#[Layout('layouts.public')]
class DetailsStepPage extends Component
{
    public function render(): View
    {
        return view('livewire.booking.details-step-page');
    }
}
