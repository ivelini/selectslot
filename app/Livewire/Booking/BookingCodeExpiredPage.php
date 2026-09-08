<?php

namespace App\Livewire\Booking;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Экран «Код устарел» (мокап booking/code-expired.html, ФТ-9): TTL кода истёк.
 * Сессионная пометка; «запросить новый код» возвращает на шаг «Код» (черновик жив).
 */
#[Layout('layouts.public')]
class BookingCodeExpiredPage extends Component
{
    public function render(): View
    {
        if (session('booking_code_expired') !== true) {
            $this->redirect(route('booking.time'));

            return view('livewire.booking.booking-code-expired-page');
        }

        return view('livewire.booking.booking-code-expired-page', [
            'codeUrl' => route('booking.code', request()->query()),
        ]);
    }
}
