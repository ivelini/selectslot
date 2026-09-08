<?php

use App\Livewire\Booking\CodeStepPage;
use App\Livewire\Booking\DetailsStepPage;
use App\Livewire\Booking\ServicesStepPage;
use App\Livewire\Booking\TimeStepPage;
use Illuminate\Support\Facades\Route;

/*
| Публичный сайт записи (ADR 0006): каждый шаг потока — отдельный full-page
| Livewire-компонент; выбор времени и услуг — query-параметры URL.
| Шаги 2–4 — заглушки (механика реализуется отдельными планами).
*/

Route::livewire('/', TimeStepPage::class)->name('booking.time');
Route::livewire('/booking/services', ServicesStepPage::class)->name('booking.services');
Route::livewire('/booking/details', DetailsStepPage::class)->name('booking.details');
Route::livewire('/booking/code', CodeStepPage::class)->name('booking.code');
