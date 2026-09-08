<section class="booking-section container">
  <h2>Выберите дату и время</h2>
  <p class="booking-subtitle">Приезжать нужно к выбранному времени начала записи</p>

  <x-booking.steps :active="1" />

  <div class="booking-row">
    <div class="booking-form">
      <div class="booking-blk">
        <h3 class="booking-blk-title">Дата</h3>
        <div class="calendar">
          <div class="calendar-header">
            <button
              type="button"
              class="calendar-nav {{ $prevMonthEnabled ? '' : 'calendar-nav--disabled' }}"
              wire:click="previousMonth"
              @disabled(! $prevMonthEnabled)
              aria-label="Предыдущий месяц"
            >‹</button>
            <div class="calendar-title">{{ $monthTitle }}</div>
            <button
              type="button"
              class="calendar-nav {{ $nextMonthEnabled ? '' : 'calendar-nav--disabled' }}"
              wire:click="nextMonth"
              @disabled(! $nextMonthEnabled)
              aria-label="Следующий месяц"
            >›</button>
          </div>
          <div class="calendar-weekdays">
            <span>Пн</span>
            <span>Вт</span>
            <span>Ср</span>
            <span>Чт</span>
            <span>Пт</span>
            <span>Сб</span>
            <span>Вс</span>
          </div>
          <div class="calendar-days">
            @for ($i = 0; $i < $calendar['leadingEmpty']; $i++)
              <span class="calendar-day calendar-day--empty"></span>
            @endfor

            @foreach ($calendar['cells'] as $cell)
              <span
                @if ($cell['selectable']) wire:click="selectDate('{{ $cell['date'] }}')" @endif
                class="{{ $cell['classes'] }}"
              >{{ (int) substr($cell['date'], 8, 2) }}</span>
            @endforeach
          </div>
          <div class="calendar-legend">
            <span class="calendar-legend-item">
              <i class="calendar-legend-dot calendar-legend-dot--selectable"></i>Свободно
            </span>
            <span class="calendar-legend-item">
              <i class="calendar-legend-dot calendar-legend-dot--off"></i>Недоступно
            </span>
            <span class="calendar-legend-item">
              <i class="calendar-legend-dot"></i>Выбрано
            </span>
          </div>
        </div>
      </div>

      <div class="booking-blk">
        <h3 class="booking-blk-title">Время</h3>
        @if ($selectedDayLabel !== null)
          <p class="booking-blk-hint">{{ $selectedDayLabel }} — выберите время начала записи</p>
          <div class="time-grid">
            @foreach ($timeSlots as $slot)
              <span
                @if (! $slot['is_closed']) wire:click="selectTime('{{ $slot['label'] }}')" @endif
                class="time-chip{{ $slot['is_closed'] ? ' time-chip--busy' : '' }}{{ $slot['selected'] ? ' time-chip--selected' : '' }}"
              >{{ $slot['label'] }}</span>
            @endforeach
          </div>
        @else
          <p class="booking-blk-hint">Выберите день в календаре, чтобы увидеть свободное время</p>
        @endif
        <p class="time-note">
          Запись на 11:00 означает, что приехать нужно к 11:00. Записаться можно не позднее чем за 1 час
          до начала и в пределах 30 дней.
        </p>
      </div>
    </div>

    <aside class="booking-summary">
      <h3 class="booking-summary-title">Ваша запись</h3>
      <div class="booking-summary-group">
        <div class="booking-summary-row">
          <span class="booking-summary-label">Дата</span>
          <span class="booking-summary-value">{{ $summaryDateLabel ?? '—' }}</span>
        </div>
        <div class="booking-summary-row">
          <span class="booking-summary-label">Время</span>
          <span class="booking-summary-value">{{ $time ?? '—' }}</span>
        </div>
      </div>
      @if ($date !== null && $time !== null)
        <a class="booking-btn-primary" wire:navigate href="{{ $servicesUrl }}">К выбору услуг</a>
      @else
        <span class="booking-btn-primary booking-btn-primary--disabled" aria-disabled="true">К выбору услуг</span>
      @endif
      <p class="booking-summary-note">
        Цена появится после выбора услуг на следующем шаге. Оплата в мастерской, после выполнения работ
      </p>
    </aside>
  </div>
</section>
