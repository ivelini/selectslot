# Шаг «Время» и каркас маршрутов публичной записи реализованы (2026-09-08)

> Source: Репозиторий TireSlot (коммит шага «Время»): routes/web.php, app/Livewire/Booking/, app/Services/SlotAvailabilityReader.php, resources/views/{layouts,livewire,components}/, public/assets/, план .claude/plans/front-shag-vremya-marshruty-zapisi.md
> Collected: 2026-09-08
> Published: 2026-09-08

Реализация первого шага публичной записи по мокапу `.template/index.html` (план согласован: схема + тест-лист, красный прогон → зелёный). Ключевые факты:

- **Маршруты** (Livewire 4.4, full-page `Route::livewire`): `booking.time` = GET / → `App\Livewire\Booking\TimeStepPage`; `booking.services` = /booking/services → ServicesStepPage; `booking.details`; `booking.code` — шаги 2–4 пока заглушки (view со степпером и пометкой «в разработке»), механика — отдельными планами.
- **Layout** `resources/views/layouts/public.blade.php`: перенос шапки/футера мокапа; ассеты скопированы `.template/assets` → `public/assets` (подключение через `asset()`); пункты «Мои записи»/«Контакты» не выведены — веток ещё нет (ссылки только по реальному потоку). Адрес/телефон в шапке-футере — статично из мокапа. Степпер — blade-компонент `<x-booking.steps :active="N">`.
- **TimeStepPage** (class-based, #[Layout('layouts.public')]): выбор зеркалится в URL через `#[Url] $date ('Y-m-d')` и `#[Url] $time ('H:00')` — F5/«назад» живы (ADR 0006). Поведение (решения пользователя):
  - чистый вход день **не выбирает** — сетка часов пуста до клика по доступному дню; календарь на текущем месяце;
  - невалидный date из query (мусор/прошедший/вне горизонта) **сбрасывается**, а не нормализуется к ближайшему дню; недоступное time (закрыт/прошло) — тоже сброс;
  - день календаря кликабелен ⇐ ∃ открытый слот в лимитах; прошедшие часы дня не показываются, закрытые (is_closed) — busy-чипами (ФТ-6); шаблон недели сайт не читает — состояние дня только по слотам;
  - кнопка «К выбору услуг» — ссылка `route('booking.services', [date, time])`, только при выбранных дате+времени (иначе span с классом `booking-btn-primary--disabled`, CSS-дополнение датированной секцией в конец `public/assets/css/style.css`);
  - листание месяцев в окне [месяц(today) .. первый день месяца ≤ горизонта]; смена дня сбрасывает время.
  - Русские названия месяцев/дней недели — массивы в классе (Carbon-локализация не подключена).
- **`App\Services\SlotAvailabilityReader`** — единый источник правила доступности для показа (НФ-4; подтверждение кода ФТ-8 в будущем использует то же правило с блокировкой). API: `daysWithAvailability(from, to): array<date, bool>` (карта по всем датам диапазона), `daySlots(date): list<{hour, is_closed}>` (от первого доступного часа), `isWithinBookingWindow(date): bool`. Границы: слот не закрыт; первый доступный час для today — начало ≥ now + min_lead_time_h с округлением вверх до часа (10:30+1 ч → 12:00); горизонт booking_horizon_days = 30 (даты today..today+29). Настройки — `Setting::find(key) ?-> value ?? default` (как SlotGridGenerator). Сравнения дат — только `whereDate` (date-каст Eloquent хранит datetime-строку, sqlite чувствителен к формату: равенство where('date', 'Y-m-d') не находит — поймано тестами).
- **Тесты**: `tests/Unit/Services/SlotAvailabilityReaderTest.php` (5, RefreshDatabase, travelTo + travelBack в tearDown) и `tests/Feature/Livewire/Booking/TimeStepPageTest.php` (7; query-параметры симулируются `Livewire::withQueryParams([...])->test(...)`). Прогоны: красный 12 failed → зелёный 23 passed (60 assertions), pint clean.
