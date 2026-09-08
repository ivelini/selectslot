<?php

namespace App\Livewire\Booking;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Экран «Время недоступно» (мокап booking/unavailable.html, ФТ-8): слот закрыт при
 * подтверждении. Сессионная пометка; выбор не теряется — кнопка ведёт к выбору времени.
 */
#[Layout('layouts.public')]
class BookingUnavailablePage extends Component
{
    public function render(): View
    {
        if (session('booking_unavailable') !== true) {
            $this->redirect(route('booking.time'));

            return view('livewire.booking.booking-unavailable-page');
        }

        return view('livewire.booking.booking-unavailable-page', [
            'timeUrl' => route('booking.time', request()->query()),
        ]);
    }
}
