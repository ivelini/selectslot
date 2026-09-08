# Шаг «Услуги»: счётчики количества, без опций (2026-09-09)

> Source: Репозиторий TireSlot: app/Livewire/Booking/ServicesStepPage.php, resources/views/livewire/booking/services-step-page.blade.php, .template/booking/services.html, план .claude/plans/prajs-po-realnomu-listu-kolichestvo.md
> Collected: 2026-09-09
> Published: 2026-09-09

Обновление шага 2 под реальный прайс (цена за единицу + количество). Ключевые факты:

- **ServicesStepPage:** свойства без hasRunflat/hasTpms; добавлен `#[Url] $quantities` (service_id => 1–4); выбор услуги даёт дефолт 4; `incrementQuantity`/`decrementQuantity` в границах 1–4; mount чистит пары «услуга–количество» (неактивные/мусорные услуги и их количества отбрасываются, количества нормализуются); URL: ?services[]=&quantities[id]=&radius=&car_type=.
- **Сайдбар:** строки выбранных услуг с счётчиком «− × N +» и итогом строки (unit × N), итог; расчёт — PricingCalculator при полном наборе (≥1 услуга ∧ радиус ∧ тип); PricingException → «Цена недоступна», переход заблокирован.
- **Карточки услуг:** базовая цена как «от N ₽» (работы) / точная (доп. работы); группа «Опции» (RunFlat/TPMS) удалена со страницы.
- **Мокап services.html:** каталог по прайсу (10 карточек), чипы R13–R21, без опций, счётчики-эталон в сайдбаре; сценарий: R16, кроссовер — снятие ×4 = 1 200 ₽, балансировка ×4 = 1 200 ₽, итог 2 400 ₽; CSS-секция .qty-control в .template/assets/css/style.css и public/assets/css/style.css.
