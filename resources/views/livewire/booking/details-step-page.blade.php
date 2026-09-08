<section class="booking-section container">
  <h2>Данные для записи</h2>
  <p class="booking-subtitle">На указанный телефон придёт SMS с кодом подтверждения</p>

  <x-booking.steps :active="3" />

  <div class="booking-row">
    <div class="booking-form">
      <div class="booking-datetime">
        <img src="{{ asset('assets/img/clock.svg') }}" alt="" />
        <span>
          <span class="booking-datetime-value">{{ $datetimeLabel }}</span>
          — прибыть к этому времени
        </span>
        <a class="booking-datetime-change" wire:navigate href="{{ $timeUrl }}">Изменить время</a>
      </div>

      <div class="booking-blk">
        <h3 class="booking-blk-title">Контактные данные</h3>
        <div class="booking-fields-row">
          <div class="auth_field">
            <label for="booking-name">Имя</label>
            <input type="text" id="booking-name" wire:model="name" placeholder="Как к вам обращаться" />
            @error('name')
              <p class="field-error">{{ $message }}</p>
            @enderror
          </div>
          <div class="auth_field">
            <label for="booking-phone">Телефон</label>
            <input type="text" id="booking-phone" wire:model="phone" placeholder="+7 (900) 000-00-00" />
            <p class="auth_field_prompt">Код из SMS придёт на этот номер</p>
            @error('phone')
              <p class="field-error">{{ $message }}</p>
            @enderror
          </div>
        </div>
        <div class="auth_field">
          <label for="booking-plate">Госномер автомобиля</label>
          <input type="text" id="booking-plate" wire:model="plate" placeholder="А 000 АА 174" />
          <p class="auth_field_prompt">Необязательно — пригодится при повторной записи</p>
        </div>
        <div class="booking-consent">
          <img src="{{ asset('assets/img/order_check.svg') }}" alt="" />
          <span>Отправляя форму, вы соглашаетесь с политикой обработки персональных данных</span>
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
              <span class="booking-summary-label">{{ $line['name'] }} × {{ $line['quantity'] }}</span>
              <span class="booking-summary-value booking-summary-value--price">{{ $line['price'] }}</span>
            </div>
          @endforeach
        </div>
        <div class="booking-summary-total">
          <span>Итого</span>
          <span class="booking-summary-total-price">{{ $quote['total'] }}</span>
        </div>
      @endif

      @error('slot')
        <p class="field-error">{{ $message }}</p>
      @enderror

      <button type="button" class="booking-btn-primary" wire:click="submit">Получить код</button>
      <a class="booking-summary-link" wire:navigate href="{{ $servicesUrl }}">Изменить услуги</a>
    </aside>
  </div>
</section>
