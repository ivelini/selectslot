<section class="booking-section container">
  @if ($data !== null)
    <div class="booking-success">
      <img class="booking-success-icon" src="{{ asset('assets/img/order_check_a.svg') }}" alt="" />
      <p class="booking-success-title">Запись подтверждена</p>
      <p class="booking-success-id">Запись № {{ $data['number'] }}</p>

      <div class="booking-details">
        <div class="booking-details-row">
          <span class="booking-details-label">Дата</span>
          <span class="booking-details-value">{{ $data['date'] }}</span>
        </div>
        <div class="booking-details-row">
          <span class="booking-details-label">Время начала</span>
          <span class="booking-details-value">{{ $data['time'] }} — приехать к началу</span>
        </div>
        <div class="booking-details-row">
          <span class="booking-details-label">Адрес</span>
          <span class="booking-details-value">г. Челябинск, Копейское шоссе, 12А</span>
        </div>
        <div class="booking-details-row">
          <span class="booking-details-label">Услуги</span>
          <span class="booking-details-value">{{ $data['services'] }}</span>
        </div>
        <div class="booking-details-row">
          <span class="booking-details-label">Параметры авто</span>
          <span class="booking-details-value">{{ $data['params'] }}</span>
        </div>
        <div class="booking-details-row booking-details-row--total">
          <span class="booking-details-label">Итого к оплате</span>
          <span class="booking-details-value">{{ $data['total'] }}</span>
        </div>
        <div class="booking-details-row">
          <span class="booking-details-label">Телефон мастерской</span>
          <span class="booking-details-value">+7 (351) 70-00-319</span>
        </div>
      </div>

      <div class="booking-success-actions">
        <a class="booking-btn-primary booking-btn-primary--auto" wire:navigate href="{{ route('booking.time') }}">На главную</a>
      </div>
      <p class="booking-success-note">
        Код из SMS также служит для входа в раздел «Моя запись» — он понадобится, если захотите отменить запись
      </p>
    </div>
  @endif
</section>
