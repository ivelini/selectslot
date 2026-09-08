<section class="booking-section container">
  <h2>Выберите услуги</h2>
  <p class="booking-subtitle">Цена в прайсе — за 1 колесо: укажите количество, итог пересчитается сразу</p>

  <x-booking.steps :active="2" />

  <div class="booking-row">
    <div class="booking-form">
      @if (count($complexes) > 0)
        <div class="booking-blk">
          <h3 class="booking-blk-title">Готовые комплексы</h3>
          <div class="complexes-grid">
            @foreach ($complexes as $complex)
              <label
                class="complex-card{{ $complex['state'] === 'full' ? ' complex-card--full' : ($complex['state'] === 'partial' ? ' complex-card--partial' : '') }}"
                wire:key="complex-{{ $complex['id'] }}"
              >
                <input
                  type="checkbox"
                  @checked($complex['state'] === 'full')
                  wire:click="toggleComplex({{ $complex['id'] }})"
                />
                <span class="complex-card-text">
                  <span class="complex-card-name">{{ $complex['name'] }}</span>
                  <span class="complex-card-note">{{ implode(', ', $complex['service_names']) }} · × 4</span>
                </span>
              </label>
            @endforeach
          </div>
        </div>
      @endif

      <div class="booking-blk">
        <h3 class="booking-blk-title">Услуги</h3>
        <div class="services-grid">
          @foreach ($catalog as $service)
            <label class="service-checkbox" wire:key="{{ $service->id }}">
              <input
                type="checkbox"
                value="{{ $service->id }}"
                @checked(in_array($service->id, $selectedIds, true))
                wire:click="toggleService({{ $service->id }})"
              />
              <span class="service-checkbox-name">{{ $service->name }}</span>
              @if (isset($unitPrices[$service->id]))
                <span class="service-checkbox-price">{{ \App\Support\Money::format($unitPrices[$service->id]) }}</span>
              @elseif (in_array($service->id, $ruleServiceIds, true))
                <span class="service-checkbox-price">от {{ \App\Support\Money::format($service->base_price) }}</span>
              @else
                <span class="service-checkbox-price">{{ \App\Support\Money::format($service->base_price) }}</span>
              @endif
            </label>
          @endforeach
        </div>
        <p class="form-hint">Выбранные услуги появятся справа — там можно изменить количество (1–4)</p>
      </div>

      <div class="booking-blk">
        <h3 class="booking-blk-title">Параметры автомобиля</h3>
        <p class="booking-blk-hint">
          От радиуса и типа автомобиля зависит цена за колесо: выберите параметры, чтобы увидеть итог
        </p>

        <div class="param-group">
          <span class="param-group-label">Радиус колеса</span>
          <div class="param-chips">
            @foreach ($radiusOptions as $option)
              <label class="param-chip" wire:key="r{{ $option->value }}">
                <input
                  type="radio"
                  name="radius"
                  value="{{ $option->value }}"
                  @checked($radius === $option->value)
                  wire:click="selectRadius({{ $option->value }})"
                />
                <span>{{ $option->label() }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="param-group">
          <span class="param-group-label">Тип автомобиля</span>
          <div class="param-chips">
            @foreach ($carTypeOptions as $option)
              <label class="param-chip" wire:key="t{{ $option->value }}">
                <input
                  type="radio"
                  name="car_type"
                  value="{{ $option->value }}"
                  @checked($carType === $option->value)
                  wire:click="selectCarType('{{ $option->value }}')"
                />
                <span>{{ $option->label() }}</span>
              </label>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <aside class="booking-summary">
      <h3 class="booking-summary-title">Ваша запись</h3>
      <div class="booking-summary-group">
        <div class="booking-summary-row">
          <span class="booking-summary-label">Дата</span>
          <span class="booking-summary-value">{{ $summaryDateLabel }}</span>
        </div>
        <div class="booking-summary-row">
          <span class="booking-summary-label">Время</span>
          <span class="booking-summary-value">{{ $time }}</span>
        </div>
      </div>

      @if ($quote !== null)
        <div class="booking-summary-group">
          @foreach ($quote['lines'] as $line)
            <div class="booking-summary-row">
              <span class="booking-summary-label">{{ $line['name'] }}</span>
              <span class="booking-summary-value booking-summary-value--price">{{ $line['price'] }}</span>
            </div>
            <div class="qty-control" wire:key="qty-{{ $line['service_id'] }}">
              <button type="button" class="qty-btn" wire:click="decrementQuantity({{ $line['service_id'] }})" aria-label="Меньше">−</button>
              <span class="qty-value">× {{ $line['quantity'] }}</span>
              <button type="button" class="qty-btn" wire:click="incrementQuantity({{ $line['service_id'] }})" aria-label="Больше">+</button>
            </div>
          @endforeach
        </div>
        <div class="booking-summary-total">
          <span>Итого</span>
          <span class="booking-summary-total-price">{{ $quote['total'] }}</span>
        </div>
        <a class="booking-btn-primary" wire:navigate href="{{ $continueUrl }}">Продолжить</a>
      @elseif ($pricingError)
        <p class="booking-summary-note">
          Цена недоступна — позвоните в мастерскую, мы запишем вас вручную
        </p>
      @else
        <p class="booking-summary-note">Выберите услуги и параметры автомобиля, чтобы увидеть итог</p>
      @endif

      <a class="booking-summary-link" wire:navigate href="{{ route('booking.time', ['date' => $date, 'time' => $time]) }}">Изменить время</a>
    </aside>
  </div>
</section>
