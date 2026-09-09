# Публичный сайт записи: маршруты ветки и шаги 1–4

> Sources: Реализация шагов «Время» и «Услуги», 2026-09-08/09; Рефакторинг доменного слоя, 2026-09-09
> Raw: [Шаг «Время» реализован](../../raw/frontend/2026-09-08-shag-vremya-realizovan.md); [Шаг «Услуги» реализован](../../raw/frontend/2026-09-08-shag-uslugi-realizovan.md); [Шаг «Услуги»: счётчики](../../raw/frontend/2026-09-09-shag-uslugi-kolichestvo.md); [Комплексы услуг](../../raw/frontend/2026-09-09-kompleksy-uslug-realizovany.md); [Шаг «Данные» реализован](../../raw/frontend/2026-09-09-shag-dannye-realizovan.md); [Шаг «Код» и создание записи](../../raw/frontend/2026-09-09-shag-kod-zapisi-realizovan.md); [Рефакторинг слоёв и конвенций](../../raw/architecture/2026-09-09-refaktoring-sloev-i-konventsii.md)

## Overview

Живая реализация публичной записи (Livewire 4.4, full-page компоненты через `Route::livewire`) поверх мокапа [.template/](public-site-mockup.md). Ветка записи: 4 маршрута `booking.time` (GET /, шаг 1 «Время»), `booking.services` (шаг 2 «Услуги»), `booking.details` (шаг 3 «Данные»), `booking.code` (шаг 4 «Подтверждение») — шаги 1–4 реализованы, плюс экраны результата `booking.success` / `booking.unavailable` / `booking.code-expired` (редиректы с flash-пометкой, ADR 0006). Общий layout `resources/views/layouts/public.blade.php`: шапка/футер из мокапа, ассеты в `public/assets` (копия `.template/assets`), степпер — компонент `<x-booking.steps :active="N">`. «Мои записи»/«Контакты» не выводятся — веток ещё нет; адрес/телефон в шапке статичны из мокапа. Общие утилиты шагов: `App\Support\RussianDate`, `App\Support\Money`, `App\Support\BookingQuery` (см. ниже). Шаги с выбором (2–4) живут в подкаталоге `App\Livewire\Booking\SelectionStepPage\` и наследуют абстрактный каркас `SelectionStepPage` (общие `#[Url]`-поля выбора; `mount`: guard «время выбираемо» → редирект на шаг 1 → нормализация выбора → `afterMount()` — специфика шага; `selectionQueryParams()` — query для route). Регэкспы/нормализация входных значений — только в `BookingQuery` и каркасе, не в компонентах.

## Шаг «Время» (TimeStepPage)

`App\Livewire\Booking\TimeStepPage`, view `livewire/booking/time-step-page`. Выбор зеркалится в query через `#[Url]`: `?date=Y-m-d&time=H:00` — F5 и «назад» живы (ADR 0006). Поведенческие контракты (решения пользователя):

- **Чистый вход день не выбирает**: календарь на текущем месяце, сетка часов пуста до клика по доступному дню.
- **Невалидный date из query сбрасывается** (мусор/прошедший/вне горизонта), а не нормализуется к ближайшему; недоступное time (закрыт/прошёл по лимиту) — тоже сброс. Валидность date — строгое чтение «Y-m-d» с round-trip (переполнение 9999-99-99 отсекается) + окно записи.
- День календаря кликабелен ⇐ есть открытый слот в лимитах (карта читателя); смена дня сбрасывает время; листание месяцев в окне [месяц(today) … горизонт].
- Часы дня: прошедшие не показываются, закрытые (`is_closed`) — busy-чипами (ФТ-6). Шаблон недели (`schedule_templates`) сайт не читает: состояние дня — только по слотам.
- Кнопка «К выбору услуг» — ссылка на `booking.services` с date/time, только при выбранных дате+времени (иначе `booking-btn-primary--disabled`, CSS-дополнение датированной секцией в `public/assets/css/style.css`).
- Русские названия месяцев/дней — утилита `App\Support\RussianDate` (Carbon-локализация не подключена).

## Шаг «Услуги» (ServicesStepPage)

`App\Livewire\Booking\SelectionStepPage\ServicesStepPage` (extends `SelectionStepPage`), view `livewire/booking/services-step-page`. Выбор зеркалится в query: `?services[]=&quantities[id]=&radius=R13..R21&car_type=` (F5/«назад», ADR 0006). Контракты:

- **Вход (каркас `SelectionStepPage`):** date/time обязаны оставаться выбираемыми (`SlotAvailabilityReader`, общий для шагов 1–4) — мусор/прошлое/закрытый слот → redirect на `booking.time`. Невалидные значения отбрасываются (`BookingQuery`): несуществующие/неактивные услуги — скоуп `Service::scopeActiveByIds` (вместе со своими количествами), радиус вне R13–R21, car_type-мусор (`carTypeValueOrNull`); количества нормализуются к 1–4 (`normalizeQuantities`, дефолт 4).
- **Количество:** цена в прайсе — за единицу (1 колесо/шт); у выбранной услуги счётчик 1–4 (дефолт 4 — сезонный комплект), `incrementQuantity`/`decrementQuantity` держат границы; итог строки = цена × количество.
- **Комплексы (`complex_services`, ADR 0008):** транзиентный выбор — блок «Готовые комплексы» над каталогом; `toggleComplex` приводит состав к ×4 (недостающие добавляет, все услуги комплекса = 4), повторный клик при полном ×4 снимает; состояние none/partial/full — дериват выбора, в URL не пишется, в запись попадает только состав.
- **Параметры:** радиус и тип обязательны к переходу («не знаю» нет, ФТ-4 v0.13); опций RunFlat/TPMS на странице нет (это доп. работы каталога, см. [Цены](../domain/pricing.md)); «Грузовик» сайт не предлагает — `CarTypeEnum::bookable()` (грузовые авто — по звонку, ФТ-18).
- **Цены:** один расчёт каталога (`CalculatePriceAction::handle` каталога с qty 1) даёт цену за единицу для **карточек услуг** — карточки показывают актуальную цену по выбранным радиусу/типу (смена параметров пересчитывает карточки живьём), у работ без выбора параметров — «от N ₽», у допработ (0 правил) — фиксированная цена. Сайдбар (строки со счётчиком «− × N +» и суммой) строится из тех же unit-цен × количества; `PricingException` ловится — «Цена недоступна — позвоните в мастерскую», кнопка заблокирована.
- **«Продолжить»** — ссылка на `booking.details` со всем выбором; «Изменить время» → `booking.time` с date/time (выбор услуг при смене времени сбрасывается — принято).
- Каталог — активные услуги реального прайса; на карточках базовая цена как «от N ₽» (работы) / точная (доп. работы).

## Шаг «Данные» (DetailsStepPage)

`App\Livewire\Booking\SelectionStepPage\DetailsStepPage` (extends `SelectionStepPage`), view `livewire/booking/details-step-page`. Персональные данные в URL не пишутся (ADR 0006): контакты — сессионный черновик `booking_draft`; выбор (время/услуги) — сквозной query (тот же уходит на шаг «Код»). Контракты:

- **Вход (каркас):** время обязано оставаться выбираемым (`SlotAvailabilityReader`), иначе → шаг 1; выбор-услуги чистится (для сайдбара-итога через `CalculatePriceAction`); возврат с шага «Код» — черновик предзаполняет форму (`afterMount`).
- **submit (ФТ-7):** валидация (имя; телефон по `App\Support\Phone` — канон «7XXXXXXXXXX»; госномер опц.) → слот всё ещё выбираем, иначе «Время недоступно» и код не шлётся → `booking_draft` {name, phone, plate} в сессию → `BookingCodeService::issue` (код 4 цифры, code_hash = sha256(код + app.key), plaintext не хранится) → `SendBookingCodeSms::dispatch` (Job, очередь+ретраи, ФТ-24) → redirect `/booking/code` с query.
- **SMS:** контракт `App\Contracts\SmsSender`, dev-`LogSmsSender` (код в лог); провайдер — отдельная реализация по `config('sms.provider')`. Технические настройки SMS — `config/sms.php`: стаб-код для ручных тестов (`sms.stub.code`, один и тот же код на все выдачи — `BookingCodeService::verify` проверяет свежайшую строку телефона с этим кодом), кулдаун повторной отправки (`sms.resend_cooldown_seconds`).
- **Возврат с шага «Код»:** черновик предзаполняет форму. Очистка черновика и создание Customer/записи — шаг 4.

## Шаг «Код» и экраны результата

`App\Livewire\Booking\SelectionStepPage\CodeStepPage` (extends `SelectionStepPage`) + `BookingSuccessPage`/`BookingUnavailablePage`/`BookingCodeExpiredPage` (view по мокапам code/success/unavailable/code-expired). Контракты:

- **Верификация:** `BookingCodeService::verify(phone, code)` → Valid/Used/Expired/Invalid (TTL = created_at + `Setting::get(SettingKeyEnum::ReservationTimeoutMin)`). **Создание:** `CreateBookingAction::handle` — транзакция с `SELECT … FOR UPDATE` слота (закрыт → SlotUnavailableException), Customer firstOrCreate, Booking confirmed со снимком (radius/car_type/plate), серверный пересчёт (цена за единицу × quantity), code.used_at атомарно; использованный код → существующая запись через `Booking::forCode(code)` (идемпотентность, НФ-1); guard на submit — только формат (`selectionDateTime`), закрытый слот уходит в действие → редирект unavailable.
- **CodeStepPage:** OTP 4 цифры; без черновика → шаг «Данные»; неверный код — ошибка; просроченный → code-expired; слот занят → unavailable; resend с кулдауном 60 с. После успеха черновик очищается, редирект success (flash booking_success_id).
- **Экраны результата** доступны только по сессионной пометке (id записи в URL нет); «Моя запись»-кнопка на success опущена (ветки нет).
- **Сквозной пронос:** шаг «Время» несёт services/quantities/radius/car_type в «К выбору услуг» — смена времени сохраняет выбор.

## Правило доступности — SlotAvailabilityReader

Единый источник правила для показа сетки (НФ-4): `App\Services\SlotAvailabilityReader` — `daysWithAvailability(from, to)` (карта «дата => есть открытый слот» по диапазону), `daySlots(date)` (строки дня от первого доступного часа), `isWithinBookingWindow(date)`, `isSelectableHour(date, hour)`. Границы: слот не закрыт; для today первый час — начало ≥ now + `min_lead_time_h` (округление вверх до часа: 10:30 + 1 ч → 12:00); горизонт `booking_horizon_days` (30: today..today+29). Настройки — `Setting::get(SettingKeyEnum::…)`; сравнения дат — только `whereDate` (date-каст Eloquent хранит datetime-строку, sqlite чувствителен к формату). Подтверждение кода (ФТ-8) должно использовать то же правило с блокировкой строки.

## Тестирование

- `tests/Unit/Services/SlotAvailabilityReaderTest.php` — границы (равенство now+min_lead доступно), скрытие прошедших, busy-закрытые, горизонт, «день доступен ⇐ открытый слот».
- `tests/Feature/Livewire/Booking/TimeStepPageTest.php` — пустая сетка при входе, восстановление из query, сброс невалидных date/time, ссылка на следующий шаг, закрытый час не выбирается, GET / отдаёт шаг.
- `tests/Unit/Actions/CalculatePriceActionTest.php` — цена за единицу × количество, подбор по радиусу/типу, 0 правил = фикс. цена, `PricingException` при отсутствии комбинации, сумма набора с разными количествами.
- `tests/Unit/Support/BookingQueryTest.php` — парсеры форматов («HH:00» — только целые часы; переполнение дат — round-trip), нормализация количеств (дефолт 4, кламп 1–4), car_type (не bookable → null).
- `tests/Feature/Livewire/Booking/ServicesStepPageTest.php` — redirect при недоступном времени, восстановление выбора с количествами, сброс мусора/осиротевших количеств, полнота набора, живой пересчёт (радиус + счётчик, границы 1–4), ссылка с полным выбором (без «Грузовика», без опций), сообщение при потерянном правиле.
- Query-параметры симулируются `Livewire::withQueryParams([...])->test(...)`; время — `travelTo` (сброс `travelBack()` в tearDown).

## See Also

- [Мокап публичного сайта](public-site-mockup.md) — эталон вёрстки для Livewire-страниц
- [Архитектура приложения](../architecture/application-architecture.md) — страницы на шаг, состояние в URL/сессии (ADR 0006)
- [Слоты: сетка времени](../domain/slot-grid.md) — откуда берутся строки и что значит доступность
