# Yandex Maps Reviews Parser

## Описание

Рабочий прототип Laravel + Vue 3 SPA для подключения карточки организации Яндекс.Карт, запуска фонового парсинга и просмотра сохраненных рейтингов, счетчиков и отзывов.

## Стек

- Laravel 13, Sanctum, Queue, Eloquent Resources, PHPUnit.
- Vue 3, Composition API, Vue Router, Pinia, Axios, Vite, Vitest.
- MySQL, Redis, Docker Compose.
- Node.js + Playwright parser как отдельный слой.

## Возможности

- Вход под сид-пользователем без регистрации.
- Валидация и нормализация ссылок `yandex.ru`, `yandex.kz`, `yandex.com`, `yandex.by`.
- SSRF-защита: запрещены не-HTTPS схемы, localhost, private/reserved IP и посторонние домены.
- Фоновый запуск `ParseYandexOrganizationJob`.
- Статусы `pending`, `processing`, `success`, `failed`.
- Сохранение организации, счетчиков и отзывов в БД.
- Дедупликация отзывов по `external_id` или fingerprint.
- Backend pagination по 50 отзывов.
- SPA polling статуса без перезагрузки страницы.

## Архитектура

Контроллеры тонкие: они валидируют запрос, вызывают action-классы и возвращают resources. Бизнес-логика вынесена в `app/Actions/Organization`, URL-защита и parser wrapper находятся в `app/Services/Yandex`, данные parser-а проходят через DTO.

Основной поток:

1. `POST /api/organization` валидирует URL через `YandexMapsUrlValidator`.
2. `YandexMapsUrlNormalizer` удаляет лишние query-параметры и сохраняет нормализованную ссылку.
3. `ParseYandexOrganizationJob` переводит организацию в `processing`.
4. `YandexOrganizationParserInterface` получает данные из fake или Playwright parser-а.
5. `PersistParsedOrganizationAction` сохраняет рейтинг, счетчики и отзывы.
6. Frontend читает данные только из Laravel API.

## Быстрый запуск через Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Приложение будет доступно на `http://localhost:8080`. Vite dev server доступен на `http://localhost:5173`.

Docker image устанавливает Node-зависимости основного frontend и `parser/`. Для реального Playwright parser-а внутри окружения дополнительно нужно установить Chromium:

```bash
docker compose exec app npm --prefix parser run install:browsers
```

## Ручной запуск без Docker

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
php artisan queue:work --tries=3 --timeout=190
```

Для локального smoke mode можно поставить `YANDEX_PARSER_MODE=fake`.

## Тестовый пользователь

```text
email: test@example.com
password: password
```

## Переменные окружения

Ключевые переменные:

```text
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
QUEUE_CONNECTION=redis
YANDEX_MAX_REVIEWS=700
YANDEX_PARSER_TIMEOUT=180
YANDEX_PARSER_MODE=playwright
YANDEX_ALLOWED_HOSTS=yandex.ru,www.yandex.ru,yandex.kz,www.yandex.kz,yandex.com,www.yandex.com,yandex.by,www.yandex.by
```

## API endpoints

- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`
- `GET /api/organization`
- `POST /api/organization`
- `POST /api/organization/refresh`
- `GET /api/organization/reviews?page=1&per_page=50`

Все endpoints, кроме login, защищены `auth:sanctum`.

## Как работает парсинг

Laravel вызывает `parser/yandex-parser.js` через Symfony Process. Скрипт запускает Chromium headless и открывает карточку с query-параметром `tab=reviews`, чтобы сразу попасть во вкладку отзывов. Если вкладка не открылась автоматически, parser пытается нажать “Отзывы” в интерфейсе.

Отзывы на Яндекс.Картах подгружаются динамически не всей страницей, а внутренней прокручиваемой панелью. Поэтому parser ищет scroll-контейнер с отзывами, прокручивает его вниз, раскрывает длинные тексты через “Читать полностью”/“Показать полностью”, нажимает “Показать ещё”, если такая кнопка появилась, и продолжает до одного из условий:

- достигнут лимит `YANDEX_MAX_REVIEWS`;
- несколько раундов подряд количество DOM-отзывов не растет;
- подходит timeout `YANDEX_PARSER_TIMEOUT`.

Средний рейтинг, число оценок и число отзывов извлекаются отдельно из текста и rating-блоков карточки. Счетчики поддерживают обычный формат и сокращения вроде `1,2 тыс.`. Parser возвращает строго JSON в stdout, технические сообщения пишет в stderr.

Парсер не использует аккаунты, внешние cookies, proxy rotation и не пытается решать капчу. Если Яндекс показывает проверку, меняет разметку или данные недоступны, job переводит организацию в `failed` и сохраняет безопасное сообщение.

## Почему используется кэширование в БД

Отзывы не грузятся с Яндекса при каждой смене страницы. Сначала данные собираются и сохраняются в БД, затем frontend получает страницы отзывов из Laravel API. Это снижает нагрузку на Яндекс, ускоряет интерфейс и позволяет дедуплицировать повторные запуски parser-а.

## Статусы парсинга

- `pending` — задача поставлена в очередь.
- `processing` — parser запущен.
- `success` — данные успешно сохранены.
- `failed` — parser завершился ошибкой или был заблокирован.

## Ограничения парсинга Яндекс.Карт

У Яндекса нет стабильного публичного API для получения всех отзывов карточки. Текущая реализация работает с динамической страницей и CSS/DOM-признаками, которые могут измениться. Антибот-защита может ограничить получение данных. Приложение не обходит капчу и не использует сомнительные способы обхода ограничений.

Для production лучше вынести parser в отдельный сервис, добавить мониторинг, алерты, отдельные retry-политики и устойчивый extraction layer.

## Безопасность

- Пароль хранится через hash.
- Login ограничен rate limiter-ом.
- Sanctum настроен для SPA-cookie auth.
- CORS поддерживает credentials только для frontend origin.
- URL validation разрешает только HTTPS-ссылки Яндекс.Карт.
- Frontend не использует `v-html` для отзывов.
- Raw parser payload не отдается frontend-у.

## Запуск тестов

```bash
php artisan test
npm run test:frontend
npm run build
```

Текущее покрытие включает auth/API, URL validation, DTO, fingerprint, fake parser persistence/deduplication, wrapper errors и базовые Vue-компоненты.

## Линтинг и форматирование

```bash
./vendor/bin/pint
```

## Деплой

Минимальный deploy flow:

1. Собрать PHP image и frontend assets.
2. Настроить MySQL, Redis и queue worker.
3. Выполнить `php artisan migrate --force --seed`.
4. Установить parser dependencies и Chromium, если используется `YANDEX_PARSER_MODE=playwright`.
5. Настроить `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, HTTPS и monitoring queue worker-а.

## Что бы я улучшил при наличии большего времени

- Поддержка нескольких организаций на пользователя.
- Плановое обновление отзывов через scheduler.
- История изменения рейтинга.
- Отдельный parser microservice.
- Более устойчивый extraction layer.
- Мониторинг ошибок парсинга.
- Dashboard для retry/backoff.
- CI/CD pipeline.
