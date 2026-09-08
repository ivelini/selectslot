COMPOSE = docker compose
EXEC    = $(COMPOSE) exec app

.PHONY: up down fresh seed

setup: install key-generate

install:
	$(EXEC) composer install

key-generate:
	$(EXEC) php artisan key:generate

## Поднять окружение (в фоне)
up:
	$(COMPOSE) up -d

## Остановить окружение
down:
	$(COMPOSE) down

## Пересоздать схему БД (drop + migrate)
fresh:
	$(EXEC) php artisan migrate:fresh --seed

## Наполнить БД сидерами
seed:
	$(EXEC) php artisan db:seed

bash:
	$(EXEC) bash
