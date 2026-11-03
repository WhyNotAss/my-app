<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Laravel Tasks API

REST API для задач на Laravel. Работает в Docker через Laravel Sail: PHP, MySQL и Redis.

## Что умеет

- `GET /api/tasks/{id}` — получить задачу. В ответе есть поле `deadline_status` (`overdue`, `due_soon`, `on_track`). Ответ кэшируется в Redis на 60 секунд, флаг `cached` показывает, пришёл ответ из кэша или из базы.
- `PATCH /api/tasks/{id}/status` — сменить статус задачи (`todo`, `in_progress`, `done`) с проверкой допустимых переходов. При успешной смене кэш задачи сбрасывается.

## Требования

- Windows с WSL 2 и Ubuntu (или Linux/macOS)
- Docker Desktop с включённой интеграцией с WSL
- Проект лучше держать внутри файловой системы WSL, например в `~/projects`, а не на диске `C:`

## Запуск

Все команды выполняются в терминале Ubuntu в папке проекта.

1. Клонировать репозиторий и перейти в папку проекта.
2. Создать файл настроек: `cp .env.example .env`
3. Установить зависимости одноразовым контейнером:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```

4. Запустить контейнеры: `./vendor/bin/sail up -d`
5. Сгенерировать ключ приложения: `./vendor/bin/sail artisan key:generate`
6. Применить миграции: `./vendor/bin/sail artisan migrate`
7. Проверить в браузере: http://localhost

## Настройки `.env`

Важные значения для работы с Redis и базой:

- `DB_HOST=mysql`
- `REDIS_HOST=redis`
- `CACHE_STORE=redis`

После изменения `.env` выполнить `./vendor/bin/sail artisan config:clear`.

## API

Во всех запросах нужен заголовок `Accept: application/json`, иначе ошибки валидации вернутся редиректом, а не кодом 422.

### GET /api/tasks/{id}

Ответ 200:

```json
{
  "data": {
    "id": 1,
    "title": "Test",
    "description": null,
    "deadline": "2026-11-02T16:15:58.000000Z",
    "status": "todo",
    "deadline_status": "due_soon"
  },
  "cached": false
}
```

При повторном запросе в течение 60 секунд `cached` будет `true`. Если задачи нет, ответ 404 и `{"message": "Task not found"}`.

### PATCH /api/tasks/{id}/status

Тело запроса:

```json
{"status": "in_progress"}
```

Ответы:

- 200 — статус изменён, в ответе обновлённая задача.
- 422 — значение не из списка, статус не передан или переход запрещён. При запрещённом переходе в ответе есть поле `allowed` со списком допустимых статусов.
- 404 — задачи нет.

## Правила переходов статусов

- `todo` → `in_progress`
- `in_progress` → `todo` или `done`
- `done` → `in_progress`

Переход из `done` в `todo` запрещён. Прямой переход `todo` → `done` тоже запрещён.

## Как работает deadline_status

Статус дедлайна хранится в колонке типа ENUM и пересчитывается при каждом сохранении задачи:

- `overdue` — дедлайн уже прошёл;
- `due_soon` — до дедлайна меньше суток;
- `on_track` — дедлайн дальше чем через сутки.

Если задачу не сохраняли, значение в базе со временем может устареть.

## Как работает кэш

- Ключ в Redis: `task:{id}`, время жизни 60 секунд.
- Флаг `cached` добавляется только при отдаче ответа и в Redis не хранится.
- Кэш сбрасывается при любом сохранении или удалении задачи через модель.

## Проверка через Postman

В репозитории лежит файл коллекции Postman. Импортируйте его через Import в Postman и запустите запросы по порядку. Адрес по умолчанию: `http://localhost/api`.

## Полезные команды

- `./vendor/bin/sail up -d` — запустить
- `./vendor/bin/sail stop` — остановить
- `./vendor/bin/sail artisan migrate` — миграции
- `./vendor/bin/sail artisan route:list --path=api` — список API-роутов
- `./vendor/bin/sail artisan tinker` — консоль PHP
