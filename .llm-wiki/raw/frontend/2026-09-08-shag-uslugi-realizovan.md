# Шаг «Услуги» публичной записи реализован (2026-09-08)

> Source: Репозиторий TireSlot: app/Livewire/Booking/ServicesStepPage.php, resources/views/livewire/booking/services-step-page.blade.php, .template/booking/services.html, план .claude/plans/front-shag-uslugi-tseny.md
> Collected: 2026-09-08
> Published: 2026-09-08

Реализация шага 2 «Услуги» по мокапу `booking/services.html` (план согласован: схема + тест-лист, красный прогон → зелёный). Ключевые факты:

- **ServicesStepPage** (class-based, #[Layout('layouts.public')]): `#[Url]`-зеркало выбора — `services[]` (id активных услуг), `radius` (R13–R22), `car_type` ('passenger'|'crossover'|'suv'), `runflat`, `tpms` (0/1) — F5/«назад» живы (ADR 0006).
- **Вход (mount)**: date/time из шага 1 обязаны оставаться выбираемыми (`Reader::isSelectableHour`) — мусор/прошлое/закрытый слот → redirect на `booking.time`. Невалидные значения (несуществующие/неактивные услуги, радиус вне R13–22, car_type-мусор) отбрасываются.
- **Расчёт**: при полном наборе (≥1 услуга ∧ радиус ∧ тип) — `PricingCalculator`; цены и итог — только при полноте; `PricingException` ловится — «Цена недоступна — позвоните в мастерскую», кнопка заблокирована.
- **«Продолжить»**: ссылка на `booking.details` со всем выбором в query; активна ⇐ полный набор и цена определена. «Изменить время» → `booking.time` с date/time (выбор услуг при смене времени сбрасывается — принято). «Грузовик»/«не знаю» на странице нет.
- **Общий код**: `Reader::isSelectableHour()` (единая проверка для шагов 1–2), утилиты `App\Support\RussianDate` (русские названия дат) и `Money` (формат «1 700 ₽», копейки) — TimeStepPage переведён на них (дубли массивов удалены).
- **Мокап services.html обновлён**: убраны «Не знаю»-чипы (радиус/тип), «Грузовик», подписи-наценки (param-chip-note, ценники опций), surcharge-строки сайдбара; строки услуг — фактические цены сценария (снятие R16+кроссовер+RunFlat = 1 300 ₽, балансировка 400 ₽, итог 1 700 ₽).
- **Тесты**: PricingCalculatorTest (4: точное правило с опциями, исключение при отсутствии комбинации, 0 правил = база, сумма набора), ServicesStepPageTest (7: redirect при недоступном времени, восстановление выбора, сброс мусора, полнота набора, живой пересчёт радиуса, ссылка/типы, ошибка прайса), DatabaseSeederTest + куб (120, R22-комбо, без truck). Прогоны: красный 11 failed → зелёный 35 passed (100 assertions), pint clean.
