<?php

namespace App\Livewire\Booking;

use App\Models\Booking\Booking;
use App\Support\Money;
use App\Support\RussianDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Экран «Запись подтверждена» (мокап booking/success.html, ФТ-11).
 * Доступен только по сессионной пометке после создания (ADR 0006): id записи в URL нет.
 */
#[Layout('layouts.public')]
class BookingSuccessPage extends Component
{
    public function render(): View
    {
        $bookingId = session('booking_success_id');
        if ($bookingId === null) {
            $this->redirect(route('booking.time'));

            return view('livewire.booking.booking-success-page', ['data' => null]);
        }

        $booking = Booking::query()->with(['items.service', 'slot', 'customer'])->find($bookingId);

        if ($booking === null) {
            $this->redirect(route('booking.time'));

            return view('livewire.booking.booking-success-page', ['data' => null]);
        }

        $slotDate = CarbonImmutable::parse($booking->slot->date->toDateString());

        return view('livewire.booking.booking-success-page', [
            'data' => [
                'number' => 'TS-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
                'date' => RussianDate::dayWithWeekday($slotDate).' '.$slotDate->year,
                'time' => substr((string) $booking->start_time, 0, 5),
                'services' => $booking->items->map(fn ($item) => $item->service->name.($item->quantity > 1 ? ' × '.$item->quantity : ''))->implode(' · '),
                'params' => 'R'.$booking->radius.', '.($booking->car_type?->label() ?? '—'),
                'total' => Money::format($booking->total_price),
            ],
        ]);
    }
}
