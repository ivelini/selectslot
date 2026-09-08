<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Запись на шиномонтаж</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />
    @livewireStyles
  </head>
  <body class="ts-body">
    <div class="app">
      <div class="wrapper">
        <header class="container">
          <div class="ts-header-row">
            <div class="logo">
              <a href="{{ route('booking.time') }}" wire:navigate>
                <img src="{{ asset('assets/img/logo.svg') }}" alt="Автоальянс" />
              </a>
            </div>
            <p class="header-tagline">Шиномонтаж · онлайн-запись</p>
            <p class="header-number">+7 (351) 70-00-319</p>
          </div>
        </header>

        {{ $slot }}

        <footer class="ts-footer">
          <div class="footer-content container">
            <div class="footer-group footer-group-center">
              <p class="footer-grup-num">+7 (351) 70-00-319</p>
              <div class="footer-group-city">2026 г. Автоальянс</div>
            </div>
            <div class="footer-group">
              <p class="footer-address">г. Челябинск, Копейское шоссе, 12А</p>
              <p class="footer-hours">Пн–Сб: 9:00–19:00
Вс: выходной</p>
            </div>
          </div>
        </footer>
      </div>
    </div>
    @livewireScripts
  </body>
</html>
