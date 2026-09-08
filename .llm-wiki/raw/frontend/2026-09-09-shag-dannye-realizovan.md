# Шаг «Данные» реализован: контакты + запрос SMS-кода (2026-09-09)

> Source: Репозиторий TireSlot: app/Livewire/Booking/DetailsStepPage.php, app/Services/BookingCodeService.php, app/Contracts/SmsSender.php, app/Services/LogSmsSender.php, app/Jobs/SendBookingCodeSms.php, app/Support/Phone.php, .template/booking/details.html, план .claude/plans/front-shag-dannye-kod.md
> Collected: 2026-09-09
> Published: 2026-09-09

Реализация шага 3 «Данные» (мокап booking/details.html → Livewire). Ключевые факты:

- **DetailsStepPage**: сквозной query-выбор (date/time/services/quantities/radius/car_type) с валидацией (время обязано быть выбираемым, иначе → шаг 1); сайдбар-итог через PricingCalculator (неполный набор/ошибка прайса — без цен). Форма: имя, телефон, госномер (опц.) — wire:model, персональные данные в URL не пишутся.
- **submit (ФТ-7)**: валидация (имя; телефон — Phone-канон) → слот всё ещё выбираем (иначе «Время недоступно», код не отправляется) → сессионный черновик `booking_draft` {name, phone, plate} (ADR 0006: контакты в сессии; предзаполняет форму при возврате; очистка — на шаге 4) → `BookingCodeService::issue` → `SendBookingCodeSms::dispatch` → redirect на `/booking/code` с query-выбором.
- **BookingCodeService::issue(phone)**: код 4 цифры, phone — канон «7XXXXXXXXXX», `code_hash` = sha256(код + app.key), plaintext не хранится; старые активные коды не аннулируются (TTL — крон). Невалидный телефон — InvalidArgumentException.
- **SMS (ФТ-24)**: контракт `App\Contracts\SmsSender` + dev-`LogSmsSender` (код в лог); биндинг в AppServiceProvider по config('services.sms.driver') (log); Job `SendBookingCodeSms` (ShouldQueue, tries 3, backoff [10,60]). Провайдер — отдельная реализация позже.
- **Phone** (App\Support): нормализация «+7 (900) 123-45-67»/«8 900…»/скобки-пробелы → «79001234567»; невалид → null. Хранение телефонов (customers, booking_codes, демо-сид, фабрики) — канон 11 цифр.
- Создание Customer и записи — шаг 4 (подтверждение кода), здесь не трогается.
- Мокап details.html: сайдбар под сценарий комплекса (×4: 1 200+800+800+1 200 = 4 000 ₽); согласие-ссылка «#» в Livewire опущена (текст).
- Тесты: Phone (2), BookingCodeService (1), DetailsStepPage (6: redirect, invalid, valid+draft+Queue+redirect, slot closed, prefill, summary). Красный 9 failed → зелёный 54 passed (183 assertions).
