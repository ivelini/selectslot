<?php

use App\Livewire\Booking\BookingCodeExpiredPage;
use App\Livewire\Booking\BookingSuccessPage;
use App\Livewire\Booking\BookingUnavailablePage;
use App\Livewire\Booking\SelectionStepPage\CodeStepPage;
use App\Livewire\Booking\SelectionStepPage\DetailsStepPage;
use App\Livewire\Booking\SelectionStepPage\ServicesStepPage;
use App\Livewire\Booking\TimeStepPage;
use Illuminate\Support\Facades\Route;

/*
| Публичный сайт записи (ADR 0006): каждый шаг потока — отдельный full-page
| Livewire-компонент; выбор времени и услуг — query-параметры URL,
| контакты клиента — сессионный черновик. Шаги 1–4 реализованы.
*/

Route::livewire('/', TimeStepPage::class)->name('booking.time');
Route::livewire('/booking/services', ServicesStepPage::class)->name('booking.services');
Route::livewire('/booking/details', DetailsStepPage::class)->name('booking.details');
Route::livewire('/booking/code', CodeStepPage::class)->name('booking.code');

// Экраны результата подтверждения (ADR 0006): редирект с сессионной пометкой, id в URL нет
Route::livewire('/booking/success', BookingSuccessPage::class)->name('booking.success');
Route::livewire('/booking/unavailable', BookingUnavailablePage::class)->name('booking.unavailable');
Route::livewire('/booking/code-expired', BookingCodeExpiredPage::class)->name('booking.code-expired');
