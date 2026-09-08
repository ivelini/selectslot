# Шаг «Данные» (GET /booking/details): контакты + запрос SMS-кода

- **Дата:** 2026-09-09
- **Статус:** согласован, реализация

## Цель

Шаг 3 публичной записи (мокап `booking/details.html` → Livewire): контактные данные + запрос кода (ФТ-7). Форма → проверка доступности времени → `booking_codes` → SMS (очередь) → переход на шаг «Код» (шаг 4 — отдельным планом).

## Решения (согласованы)

- **SMS:** контракт `App\Contracts\SmsSender` + dev-реализация `LogSmsSender` (код в лог); отправка — `SendBookingCodeSms` (Job, очередь + ретраи, ФТ-24); провайдер подключится отдельной реализацией.
- **Телефон:** канон `7XXXXXXXXXX` (11 цифр; «+7»/«8»/скобки/пробелы принимаются) — `App\Support\Phone`; хранение канона в `Customer.phone`/`BookingCode.phone`; сиды/фабрики правятся.
- **Черновик (ADR 0006):** сессионный `booking_draft` {name, phone, plate}: создаётся при submit шага 3, предзаполняет форму при возврате, живёт до подтверждения (очистка — шаг 4).
- **Коды:** каждый запрос — новый код (4 цифры, code_hash = sha256(код + app.key)); старые активные коды не аннулируются (крон по TTL).
- Границы: создание `Customer` и записи — шаг 4; неполный выбор услуг на входе шага 3 не блокируется (проверка при подтверждении); согласие-ссылка «#» в Livewire опускается.

## Задачи

1. `App\Support\Phone`, `App\Contracts\SmsSender` + `LogSmsSender` (bind по `services.sms.driver`), `App\Jobs\SendBookingCodeSms`, `App\Services\BookingCodeService::issue(phone)`.
2. `DetailsStepPage` (+view): сквозной query-выбор (сайдбар-итог), поля wire:model, submit (валидация → слот выбираем → черновик → issue → Job → redirect booking.code с query).
3. Данные: `CustomerFactory`, демо-телефоны (DemoBookingSeeder) → канон.
4. Мокап `details.html`: сайдбар под актуальный сценарий (комплекс ×4: 1 200+800+800+1 200 = 4 000 ₽, без surcharge-строк).
5. Документация: ФТ — без изменений (ФТ-7/ФТ-23/ФТ-24 покрывают); вики — после ответа на вопрос.

## Тест-лист (согласован)

```
── Phone (tests/Unit/Support/PhoneTest.php) ───────────────────────────────────────

Тест 1
1. Поведение: нормализация ввода к канону «7XXXXXXXXXX»: скобки/пробелы/дефисы/«+7»/«8» принимаются
2. Вход: «+7 (900) 123-45-67», «8 900 123-45-67», «89001234567», «+7 900 000-00-00»
3. Ожидание: все → «79001234567» / «79000000000»
4. Имя: test_normalize_accepts_common_input_formats

Тест 2
1. Поведение: невалидный телефон (не 11 цифр, не 7/8 в начале, буквы) → null
2. Вход: «123», «9001234567» (10), «+7 (900) 123-45-6», «abc»
3. Ожидание: null
4. Имя: test_normalize_rejects_invalid_phones

── BookingCodeService (tests/Unit/Services/BookingCodeServiceTest.php) ────────────

Тест 3
1. Поведение: issue создаёт строку кода: 4 цифры, phone в каноне, code_hash = sha256(код+соль); plaintext в БД не хранится
2. Вход: issue(«+7 (900) 123-45-67»)
3. Ожидание: возвращён 4-значный код; booking_codes: phone=79001234567, used_at null, hash совпадает с sha256
4. Имя: test_issue_creates_hashed_code_with_canonical_phone

── DetailsStepPage (tests/Feature/Livewire/Booking/DetailsStepPageTest.php) ───────

Тест 4
1. Поведение: вход без валидного выбора времени → redirect на шаг 1
2. Вход: date вчера / закрытый слот / время не передано
3. Ожидание: redirect booking.time
4. Имя: test_redirects_to_time_step_when_slot_unavailable

Тест 5
1. Поведение: submit с невалидными полями не создаёт код
2. Вход: name='', phone='123'
3. Ожидание: booking_codes пуст; ошибки валидации
4. Имя: test_submit_invalid_does_not_issue_code

Тест 6
1. Поведение: submit валидный: код создан (телефон-канон), черновик в сессии, SMS-Job отправлен, редирект на booking.code с сохранённым query-выбором
2. Вход: полный выбор в query; name=«Иван», phone=«+7 (900) 123-45-67», plate=«А 000 АА 174»
3. Ожидание: BookingCode phone=79001234567; сессия draft; assertPushed(SendBookingCodeSms); assertRedirect(booking.code + query)
4. Имя: test_submit_valid_issues_code_saves_draft_and_redirects

Тест 7
1. Поведение: слот закрылся до submit → код не создаётся, сообщение «Время недоступно», без редиректа
2. Вход: слот открыт при mount, закрыт до submit
3. Ожидание: booking_codes пусто, сообщение, redirect не выполнен
4. Имя: test_submit_when_slot_closed_shows_unavailable

Тест 8
1. Поведение: черновик из сессии предзаполняет форму; plate пуст → null
2. Вход: сессия draft {name, phone, plate:null}
3. Ожидание: поля name/phone установлены из черновика
4. Имя: test_draft_from_session_prefills_form

Тест 9
1. Поведение: сайдбар показывает итог выбранного набора (состав × qty)
2. Вход: полный выбор (комплекс) в query
3. Ожидание: в сайдбаре строки ×4 и сумма; на странице нет чипов выбора услуг
4. Имя: test_summary_shows_selection_total
```

## Прогоны

**Красный** (до файлов реализации): `9 failed` — Class "App\Support\Phone" not found, BindingResolutionException (BookingCodeService), DetailsStepPage-заглушка без свойств (PublicPropertyNotFoundException).

**Зелёный** (после реализации): `Tests: 54 passed (183 assertions)` — 9 новых тестов шага «Данные» + регресс всех прежних; pint clean.
