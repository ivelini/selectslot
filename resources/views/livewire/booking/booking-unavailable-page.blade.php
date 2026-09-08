<section class="booking-section container">
  <div class="code-card">
    <h3>Код из SMS</h3>
    <div class="alert alert--error">
      <img src="{{ asset('assets/img/warning.svg') }}" alt="" />
      <div>
        <p class="alert-title">Выбранное время больше недоступно</p>
        <p class="alert-text">
          Слот только что закрыли (перерыв или поломка). Запись не создана — ничего не удержано и не списано.
          Выберите другое время: услуги и данные сохранятся, придёт новый код.
        </p>
      </div>
    </div>
    <a class="booking-btn-primary" wire:navigate href="{{ $timeUrl ?? route('booking.time') }}">Выбрать другое время</a>
  </div>
</section>
