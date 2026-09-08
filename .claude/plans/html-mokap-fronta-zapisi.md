# План: статичный HTML-мокап `.template/` для TireSlot

## Контекст

TireSlot — сервис онлайн-записи на шиномонтаж (Laravel: Filament + Livewire), стадия проектирования. Нужен эталон вёрстки публичного сайта записи — по образцу соседнего мокапа магазина шин `/home/perminoviv/my/imsd-ai/template/.template/` (статичный HTML без JS, стили в `assets/css/style.css`, экран = файл, каждая страница самодостаточна).

Решения пользователя: главная = шаг 1 записи (услуги + авто); доп. экраны — «Моя запись» (вход → код → список → отмена), состояния ошибок (время недоступно, код просрочен), контакты; бренд — «Автоальянс» (logo.svg из исходника). Поток из ФТ v0.10: услуги+параметры → дата/время → данные (SMS-код) → подтверждение → детали записи.

Стек страниц: без JS, CSS-only состояния (`:has()`, модификаторы), desktop-first, `lang="ru"`. Юнит-тесты не применимы (статичный мокап) — верификация по чеклисту в браузере.

## Структура файлов

```
tireslot/.template/
├── index.html                  # Шаг 1: дата и время (он же главная; порядок «время → услуги» — решение пользователя)
├── contacts.html
├── booking/
│   ├── services.html           # Шаг 2: услуги + параметры авто
│   ├── details.html            # Шаг 3: имя/телефон/госномер (шаг 1 «Время» остаётся index)
│   ├── code.html               # Шаг 4: SMS-код (OTP)
│   ├── success.html            # Детали записи (ФТ-11)
│   ├── unavailable.html        # Ошибка: время недоступно (ФТ-8)
│   └── code-expired.html       # Ошибка: код просрочен (ФТ-9)
├── my-booking/
│   ├── index.html              # Вход по телефону
│   ├── code.html               # Ввод кода (верификация входа, ФТ-14)
│   ├── list.html               # Список записей (состояния отмены)
│   └── cancelled.html          # Запись отменена
└── assets/                     # копия из template/.template/ целиком
    ├── css/style.css           # + датированные дополнения в конец (исходное не трогать)
    ├── fonts/
    └── img/                    # + новые SVG: calendar, clock, phone, warning
```

## Общий скелет

- **Шапка** (упрощённая от исходной): ряд 1 — `.header-number` (телефон) + `.header-my-booking` («Моя запись», иконка login.svg); ряд 2 — `.logo` (logo.svg → index.html) + `.header-tagline`. Убраны: город, навигация, бургер, поиск, каталог, корзина, логин, гео-модалка.
- **Футер**: телефон + «Заказать звонок» (`.footer-grup-num`, `.footer-get-call`), адрес/часы (`.footer-address`, `.footer-hours`), ссылки «Контакты»/«Моя запись» (`.footer-group-link`), соцсети (`.social-row`).
- **Степпер** (новый компонент): `.steps > .step` c модификаторами `--done` (ссылка назад), `--active` (красный кружок), `--next`; `.step-num`, `.step-title`, соединители `.step::after`. Шаги: Услуги → Время → Данные → Подтверждение. На ≤639px остаются кружки.
- Все страницы: `<div class="app"><div class="wrapper">`, шапка → контент → футер, пути `../assets/...` в подпапках.

## Экраны (ключевое)

1. **index.html** — `.booking-row` (форма + сайдбар-итог `.booking-summary`): карточка услуг `.services-grid` из `.service-checkbox` (надстройка над `.custom-checkbox-container`, цена справа: 4–5 услуг с ценами); карточка параметров: радиус `.param-chips`/`.param-chip` (R13–R22 + «Не знаю», скрытые radio, `:has(input:checked)`), тип авто (те же чипы), опции RunFlat/TPMS (чекбокс-карточки); `.form-hint` про «не знаю»; итог с ценой + `.primary-button` «Выбрать время» → booking/time.html; «Оплата в мастерской после работ».
2. **booking/time.html** — календарь `.calendar` (сентябрь 2026, 1 сен = вторник: первая ячейка `--empty`; дни `--past`/`--off` (вс)/`--today`/`--selected`, навигация `--disabled` назад, легенда); сетка часов `.time-grid`/`.time-chip` (9:00–18:00, 13:00 `--busy` — обед, выбранный `--selected`); `.time-note` («прибыть к выбранному времени», горизонт 30 дней, мин. 1 ч); сайдбар: дата/время/итог + «Продолжить» → booking/details.html.
3. **booking/details.html** — `.auth_field` поля: Имя*, Телефон* («Код из SMS придёт на этот номер»), Госномер (необязательно); блок `.booking-datetime` («11 сентября, 11:00» + «Изменить»); строка согласия (`.order_form_method_btn_info`); кнопка «Получить код» → booking/code.html.
4. **booking/code.html** — центрированная `.code-card`: «SMS отправлен на +7 951 123-45-67», OTP `.otp-inputs`/`.otp-digit` (4 поля по 56px, `:focus` красный, `--error` вариант), `.code-timer` («повторно через 0:14»), `.code-resend` (неактивна), «Код действует 15 минут»; «Подтвердить запись» → booking/success.html; «запись создастся только после ввода кода».
5. **booking/success.html** — `.success-card`: order_check_a.svg 64px, «Запись подтверждена», № TS-000123; `.booking-details` строки: дата, время начала (прибыть к), адрес, состав услуг, итог, телефон мастерской; кнопки «На главную» / «Моя запись»; приписка про код-верификатор.
6. **booking/unavailable.html** — OTP с `.otp-digit--error` + `.alert--error` (warning.svg): «Время больше недоступно. Запись не создана. Выберите другое время.» → booking/time.html; «Подтвердить» — disabled.
7. **booking/code-expired.html** — пустые otp, `.alert--error` «Код устарел», «Запросите новый код» → booking/code.html + «Ввести другой номер» → booking/details.html.
8. **my-booking/index.html** — `.auth_card`: телефон + «Получить код» → my-booking/code.html; «Нет записи? Записаться».
9. **my-booking/code.html** — `.auth_card` + OTP: «Код работает, пока запись активна» → my-booking/list.html.
10. **my-booking/list.html** — карточки `.booking-card` (дата-бейдж, время, адрес, услуги, цена, `.status-badge`); 3 состояния: confirmed с кнопкой «Отменить» (>2 ч), confirmed с блоком «Отмена недоступна (<2 ч) — позвоните», done (серый, без кнопки).
11. **my-booking/cancelled.html** — `.success-card`: «Запись отменена, время освобождено» + детали + «Записаться снова» / «Мои записи».
12. **contacts.html** — `.contacts-card`: адрес, телефон, часы (Пн–Сб 9:00–18:00, Вс — выходной), соцсети, `.map-placeholder` («Карта проезда — здесь будет виджет»), «Записаться онлайн».

## CSS-дополнения в конец style.css

Датированные секции `/* ===== <Название> (<страницы>) — 04.09.2026 ===== */`, исходные 4701 строк не трогать. Инвентарь: каркас (`.booking-row`), сайдбар (`.booking-summary*`), кнопки (`.secondary-button`, `.primary-button:disabled`), степпер, услуги (`.services-grid`, `.service-checkbox*`), параметры (`.param-chips`, `.param-chip`), календарь (`.calendar*`), время (`.time-grid`, `.time-chip*`), данные (`.booking-fields-row`, `.booking-datetime*`), OTP (`.code-card*`, `.otp-*`), алерты (`.alert*`), успех/детали (`.success-card*`, `.booking-details*`, `.booking-id`), «Моя запись» (`.booking-list`, `.booking-card*`, `.status-badge*`), контакты (`.contacts-*`, `.map-placeholder`), шапка/футер (`.header-my-booking`, `.header-tagline`, `.footer-address`, `.footer-hours`), адаптив новых блоков (1365/1023/639).

## Порядок работ

1. Создать `.template/`, скопировать `assets/` целиком; добавить новые SVG (calendar, clock, phone, warning).
2. CSS-фундамент: секции каркаса, сайдбара, кнопок, степпера, шапки/футера.
3. `index.html` — эталон скелета (шапка/футер/степпер) + секции услуг/параметров.
4. Поток: time → details → code → success (+ их CSS-секции).
5. Ошибки: unavailable, code-expired (+ `.alert`).
6. «Моя запись»: index → code → list → cancelled.
7. `contacts.html`.
8. Финальный проход: адаптив, чеклист, вычитка ссылок.

## Верификация

Пройти руками все маршруты: index → time → details → code → success; ошибки → возвраты; my-booking: index → code → list → cancelled. Проверить: 1 сентября 2026 — вторник (пустышка под Пн), воскресенья `--off`, 13:00 `--busy`; OTP: 4 поля, `:focus`, `--error`; статус-бейджи confirmed/done; степпер правильный на каждой странице; шапка/футер идентичны; нет горизонтального скролла на 1366/1024/640; нет JS; `lang="ru"` везде.

После реализации: перенести план в `.claude/plans/` проекта (глобальное правило хранения планов), обновить CLAUDE.md (описание `.template/` в карте проекта).
