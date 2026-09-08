# Публичный сайт записи: маршруты ветки и шаг «Время»

> Sources: Реализация шага «Время» и каркаса маршрутов, 2026-09-08
> Raw: [Шаг «Время» реализован](../../raw/frontend/2026-09-08-shag-vremya-realizovan.md)

## Overview

Живая реализация публичной записи (Livewire 4.4, full-page компоненты через `Route::livewire`) поверх мокапа [.template/](public-site-mockup.md). Ветка записи: 4 маршрута `booking.time` (GET /, шаг 1 «Время»), `booking.services`, `booking.details`, `booking.code` (шаги 2–4 — заглушки со степпером, механика отдельными планами). Общий layout `resources/views/layouts/public.blade.php`: шапка/футер из мокапа, ассеты в `public/assets` (копия `.template/assets`), степпер — компонент `<x-booking.steps :active="N">`. «Мои записи»/«Контакты» не выводятся — веток ещё нет; адрес/телефон в шапке статичны из мокапа.

## Шаг «Время» (TimeStepPage)

`App\Livewire\Booking\TimeStepPage`, view `livewire/booking/time-step-page`. Выбор зеркалится в query через `#[Url]`: `?date=Y-m-d&time=H:00` — F5 и «назад» живы (ADR 0006). Поведенческие контракты (решения пользователя):

- **Чистый вход день не выбирает**: календарь на текущем месяце, сетка часов пуста до клика по доступному дню.
- **Невалидный date из query сбрасывается** (мусор/прошедший/вне горизонта), а не нормализуется к ближайшему; недоступное time (закрыт/прошёл по лимиту) — тоже сброс. Валидность date — строгое чтение «Y-m-d» с round-trip (переполнение 9999-99-99 отсекается) + окно записи.
- День календаря кликабелен ⇐ есть открытый слот в лимитах (карта читателя); смена дня сбрасывает время; листание месяцев в окне [месяц(today) … горизонт].
- Часы дня: прошедшие не показываются, закрытые (`is_closed`) — busy-чипами (ФТ-6). Шаблон недели (`schedule_templates`) сайт не читает: состояние дня — только по слотам.
- Кнопка «К выбору услуг» — ссылка на `booking.services` с date/time, только при выбранных дате+времени (иначе `booking-btn-primary--disabled`, CSS-дополнение датированной секцией в `public/assets/css/style.css`).
- Русские названия месяцев/дней — массивы в классе (Carbon-локализация не подключена).

## Правило доступности — SlotAvailabilityReader

Единый источник правила для показа сетки (НФ-4): `App\Services\SlotAvailabilityReader` — `daysWithAvailability(from, to)` (карта «дата => есть открытый слот» по диапазону), `daySlots(date)` (строки дня от первого доступного часа), `isWithinBookingWindow(date)`. Границы: слот не закрыт; для today первый час — начало ≥ now + `min_lead_time_h` (округление вверх до часа: 10:30 + 1 ч → 12:00); горизонт `booking_horizon_days` (30: today..today+29). Настройки — `Setting::find(key)?->value ?? default`; сравнения дат — только `whereDate` (date-каст Eloquent хранит datetime-строку, sqlite чувствителен к формату). Подтверждение кода (ФТ-8) должно использовать то же правило с блокировкой строки.

## Тестирование

- `tests/Unit/Services/SlotAvailabilityReaderTest.php` — границы (равенство now+min_lead доступно), скрытие прошедших, busy-закрытые, горизонт, «день доступен ⇐ открытый слот».
- `tests/Feature/Livewire/Booking/TimeStepPageTest.php` — пустая сетка при входе, восстановление из query, сброс невалидных date/time, ссылка на следующий шаг, закрытый час не выбирается, GET / отдаёт шаг. Query-параметры симулируются `Livewire::withQueryParams([...])->test(...)`; время — `travelTo` (сброс `travelBack()` в tearDown).

## See Also

- [Мокап публичного сайта](public-site-mockup.md) — эталон вёрстки для Livewire-страниц
- [Архитектура приложения](../architecture/application-architecture.md) — страницы на шаг, состояние в URL/сессии (ADR 0006)
- [Слоты: сетка времени](../domain/slot-grid.md) — откуда берутся строки и что значит доступность
