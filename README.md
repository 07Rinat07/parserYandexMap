# Yandex Maps Reviews Parser

Рабочий прототип Laravel + Vue 3 SPA для подключения карточки организации на Яндекс.Картах, фонового парсинга отзывов и просмотра сохраненных рейтингов, счетчиков, отзывов, истории и экспорта данных.

## Что умеет приложение

- Авторизация через Laravel Sanctum без регистрации, с одним сид-пользователем.
- Ввод ссылки на карточку организации Яндекс.Карт.
- Валидация и нормализация ссылок `yandex.ru`, `yandex.kz`, `yandex.com`, `yandex.by`.
- SSRF-защита: запрещены не-HTTPS ссылки, localhost, private/reserved IP и посторонние домены.
- Фоновый парсинг через Laravel Queue + Redis.
- Parser microservice на Node.js + Playwright + Chromium.
- Сохранение организации, рейтинга, количества оценок, количества отзывов и отзывов в MySQL.
- Постраничный вывод отзывов по 50 записей без перезагрузки страницы.
- Поддержка нескольких организаций на одного пользователя.
- Дедупликация отзывов по `external_id` или fingerprint.
- История рейтинга и счетчиков после каждого успешного парсинга.
- Мониторинг статусов parser-а и retry для проблемных карточек.
- Экспорт данных по организации в настоящий `XLSX`, `CSV для Excel`, `JSON` и `TXT`.
- Защита от повторного запуска одинакового парсинга, пока карточка уже `pending` или `processing`.

## Быстрый запуск для проверки

Нужны Docker и Docker Compose.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

После запуска:

```text
Приложение:     http://localhost:8080
Parser health:  http://localhost:3000/health
Vite dev:       http://localhost:5173
MySQL host:     127.0.0.1:3307
Redis host:     127.0.0.1:6380
```

Тестовый пользователь:

```text
email:    test@example.com
password: password
```

Если приложение уже поднималось раньше и в браузере остались старые cookies, после изменения `.env` лучше выйти из аккаунта, обновить страницу или открыть `http://localhost:8080` в инкогнито.

## Ссылки для ручной проверки

Можно вставить любую карточку организации Яндекс.Карт. Для smoke-теста подходят проверенные карточки:

```text
https://yandex.kz/maps/org/moskvarium/1367420415/reviews/
```

```text
https://yandex.kz/maps/org/tretyakovskaya_galereya/21117108341/reviews/
```

```text
https://yandex.kz/maps/org/khalyq_sharuashylyghy_zhetistikterining_kormesi/149076928950/reviews/
```

Дополнительно можно проверить любую небольшую локальную организацию: чем меньше отзывов, тем быстрее smoke-run. Важно вставлять именно ссылку на карточку `/maps/org/...`, а не поисковую выдачу.

Обычно первый результат появляется через 30-60 секунд. Во время работы будет статус `Ожидает` или `В работе`; после успешного завершения появятся рейтинг, счетчики, отзывы, история и экспорт.

## Как проверять вручную

1. Открыть `http://localhost:8080`.
2. Войти под `test@example.com / password`.
3. Вставить ссылку на организацию.
4. Нажать `Сохранить`.
5. Дождаться статуса `Готово`.
6. Проверить:
   - название организации;
   - средний рейтинг;
   - количество оценок;
   - количество отзывов;
   - список отзывов;
   - пагинацию по 50 отзывов;
   - историю рейтинга;
   - экспорт XLSX/CSV/JSON/TXT.

## Docker-сервисы

`docker-compose.yml` поднимает:

- `nginx` — HTTP entrypoint на `localhost:8080`;
- `app` — PHP-FPM Laravel;
- `queue` — Laravel queue worker;
- `scheduler` — Laravel scheduler loop;
- `parser` — Node.js Playwright parser microservice на `localhost:3000`;
- `db` — MySQL 8.4, снаружи `127.0.0.1:3307`;
- `redis` — Redis 7, снаружи `127.0.0.1:6380`;
- `node` — Vite dev server на `localhost:5173`.

Для зависимостей используются named volumes:

- `vendor`;
- `node_modules`;
- `parser_node_modules`;
- `db_data`.

Это нужно, чтобы bind mount исходников не перекрывал зависимости, установленные внутри Docker image.

## Важные команды

Пересобрать и поднять проект:

```bash
docker compose up -d --build
```

Применить миграции и сид:

```bash
docker compose exec app php artisan migrate --seed
```

Полностью пересоздать таблицы локально:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Посмотреть контейнеры:

```bash
docker compose ps
```

Логи Laravel/очереди/parser-а:

```bash
docker compose logs -f app queue parser
```

Проверить parser health:

```bash
curl http://localhost:3000/health
```

Остановить проект:

```bash
docker compose down
```

Остановить и удалить данные MySQL:

```bash
docker compose down -v
```

## Как работает основной поток

1. Frontend отправляет `POST /api/organization` с `yandex_url`.
2. Laravel валидирует ссылку через `StoreYandexOrganizationRequest` и `YandexMapsUrlValidator`.
3. `YandexMapsUrlNormalizer` приводит ссылку к стабильному виду.
4. `SaveYandexOrganizationAction` сохраняет или обновляет запись в `organizations`.
5. Если карточка уже `pending` или `processing`, новая такая же задача не создается.
6. В Redis-очередь отправляется `ParseYandexOrganizationJob`.
7. Queue worker переводит организацию в `processing` и обновляет `parser_metadata.progress`.
8. Laravel вызывает parser microservice `http://parser:3000/parse`.
9. Parser открывает Яндекс.Карты в Chromium, переходит к отзывам, прокручивает список и собирает доступные отзывы.
10. `PersistParsedOrganizationAction` сохраняет рейтинг, счетчики, отзывы и снимок истории.
11. Frontend polling-ом обновляет статус, progress и показывает данные без перезагрузки страницы.

Контроллеры остаются тонкими: бизнес-логика лежит в `app/Actions`, parser wrappers в `app/Services/Yandex`, экспорт в `app/Services/Exports`.

## Где сохраняются данные

Основная БД: MySQL, database `yandex_reviews`.

Подключение с хоста:

```text
host:     127.0.0.1
port:     3307
database: yandex_reviews
user:     app
password: password
```

Основные таблицы:

- `users` — сид-пользователь.
- `organizations` — ссылка, нормализованная ссылка, название, рейтинг, счетчики, статус parser-а, metadata parser-а, дата последнего парсинга.
- `reviews` — автор, дата, текст, оценка, fingerprint, raw payload.
- `rating_snapshots` — история рейтинга, количества оценок и количества отзывов.
- `jobs` — очередь Laravel, если используется database queue.
- `cache`, `sessions` — Laravel cache/session storage.

Отзывы не парсятся заново при каждой смене страницы. Они кэшируются в БД, а UI берет страницы отзывов из Laravel API.

## Экспорт данных

После успешного парсинга в интерфейсе появляется блок `Экспорт данных`.

Доступные форматы:

- `XLSX` — настоящий Excel workbook OpenXML с листами `Организация`, `Отзывы`, `История`.
- `CSV для Excel` — файл с UTF-8 BOM и разделителем `;`, удобно открывается в Excel/LibreOffice.
- `JSON` — структурированные данные: организация, отзывы, история рейтинга.
- `TXT` — простой текстовый отчет.

API endpoint:

```text
GET /api/organizations/{id}/export?format=xlsx
GET /api/organizations/{id}/export?format=csv
GET /api/organizations/{id}/export?format=json
GET /api/organizations/{id}/export?format=txt
```

Endpoint защищен `auth:sanctum` и отдает данные только владельцу организации.

## История рейтинга

После каждого успешного парсинга создается запись в `rating_snapshots`.

В интерфейсе показываются:

- текущий рейтинг;
- изменение рейтинга относительно предыдущего снимка;
- текущее количество оценок и отзывов;
- прирост оценок и отзывов;
- график рейтинга;
- столбцы прироста отзывов;
- список последних снимков.

Если рейтинг у организации не меняется, график будет ровным. Это нормальное состояние, поэтому UI дополнительно показывает текст `без изменений` и дельты по оценкам/отзывам.

Для демонстрации истории есть кнопка `Снимок`: она сохраняет текущие рейтинг/счетчики в `rating_snapshots` без нового парсинга.

## API endpoints

Публичный endpoint:

```text
POST /api/login
```

Защищенные Sanctum endpoints:

```text
POST /api/logout
GET  /api/me

GET  /api/organization
POST /api/organization
POST /api/organization/refresh
GET  /api/organization/reviews?page=1&per_page=50

GET  /api/organizations
GET  /api/organizations/{id}
POST /api/organizations/{id}/refresh
GET  /api/organizations/{id}/reviews?page=1&per_page=50
GET  /api/organizations/{id}/rating-history
POST /api/organizations/{id}/rating-history
GET  /api/organizations/{id}/export?format=xlsx|csv|json|txt

GET  /api/parser-monitoring
```

## Как работает парсер

Официального API для отзывов Яндекс.Карт нет, поэтому данные собираются через headless Chromium.

Parser microservice:

- находится в `parser/server.js`;
- использует Playwright;
- держит browser pool;
- принимает задачи через HTTP;
- хранит очередь в Redis;
- возвращает JSON с организацией, рейтингом, счетчиками и отзывами.

Логика извлечения:

- `parser/parse-core.js` — orchestration, прокрутка, ожидания, нормализация;
- `parser/extraction.js` — CSS/DOM selectors и извлечение данных;
- `parser/browser-pool.js` — пул Chromium;
- `parser/persistent-queue.js` — очередь parser-а.

Parser открывает карточку, пытается попасть во вкладку отзывов, ищет внутренний scroll-контейнер, прокручивает его, раскрывает длинные тексты, нажимает `Показать еще`, если кнопка доступна, и продолжает до одного из условий:

- достигнут `YANDEX_MAX_REVIEWS`;
- несколько раундов подряд новые отзывы не появляются;
- подходит timeout `YANDEX_PARSER_TIMEOUT`.

Parser не использует аккаунты, внешние cookies, proxy rotation и не решает капчу. Если Яндекс показывает защиту, меняет DOM или ответ пустой, задача переводится в `failed`, а ошибка показывается в интерфейсе мониторинга.

## Parser confidence и diagnostics

Каждый parser result содержит metadata:

- `contract_version`;
- `strategy`;
- `confidence`;
- `warnings`;
- `diagnostics`.
- `progress`.

Laravel сохраняет это в `organizations.parser_metadata` и `organizations.parser_confidence`.

Если `confidence` ниже `YANDEX_MINIMUM_PARSER_CONFIDENCE`, результат считается небезопасным и организация переводится в `failed`. Это нужно, чтобы при изменении DOM Яндекса не сохранять мусорные данные как успешные.

В UI есть раскрываемый блок `Parser diagnostics`: stage/message progress, `reviews_seen`, strategy, contract version, elapsed time, warnings и selector hits. Это помогает быстро понять, сломался ли DOM-контракт Яндекса или parser просто не нашел новые отзывы.

## Переменные окружения

Главные переменные для Docker-сценария:

```text
APP_URL=http://localhost
FRONTEND_URL=http://localhost:8080,http://127.0.0.1:8080,http://localhost:5173,http://127.0.0.1:5173
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,localhost:5173,127.0.0.1,127.0.0.1:8080,127.0.0.1:5173

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=yandex_reviews
DB_USERNAME=app
DB_PASSWORD=password

QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

YANDEX_MAX_REVIEWS=700
YANDEX_PARSER_TIMEOUT=180
YANDEX_MINIMUM_PARSER_CONFIDENCE=30
YANDEX_PARSER_MODE=microservice
YANDEX_PARSER_SERVICE_URL=http://parser:3000
YANDEX_ALLOWED_HOSTS=yandex.ru,www.yandex.ru,yandex.kz,www.yandex.kz,yandex.com,www.yandex.com,yandex.by,www.yandex.by

PARSER_PORT=3000
PARSER_CONCURRENCY=2
PARSER_BROWSER_POOL_SIZE=2
PARSER_BROWSER_MAX_TASKS=50
PARSER_BROWSER_MAX_ERRORS=5
PARSER_MAX_QUEUE_SIZE=50
PARSER_SYNC_WAIT_MS=180000
PARSER_QUEUE_DRIVER=redis
PARSER_REDIS_URL=redis://redis:6379
PARSER_REDIS_PREFIX=yandex-parser
```

Для локальных unit/smoke-тестов без настоящего Яндекса можно использовать:

```text
YANDEX_PARSER_MODE=fake
```

## Ручной запуск без Docker

Docker является основным способом проверки. Ручной запуск нужен только для разработки.

```bash
cp .env.example .env
composer install
npm install
npm --prefix parser install
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
php artisan queue:work --tries=3 --timeout=190
```

Для parser microservice отдельно:

```bash
npm --prefix parser run serve
```

Для реального Playwright-парсинга без Docker нужно установить браузер:

```bash
npx --prefix parser playwright install --with-deps chromium
```

## Тесты

```bash
php artisan test
npm run test:frontend
npm run build
npm run test:e2e
node --check parser/yandex-parser.js
node --check parser/server.js
node --check parser/parse-core.js
node --check parser/browser-pool.js
node --check parser/persistent-queue.js
```

`npm run test:e2e` запускается против уже поднятого приложения. По умолчанию использует `http://localhost:8080`, `test@example.com / password` и smoke-ссылку на Москвариум. Настройки:

```text
E2E_BASE_URL=http://localhost:8080
E2E_EMAIL=test@example.com
E2E_PASSWORD=password
E2E_YANDEX_URL=https://yandex.kz/maps/org/moskvarium/1367420415/reviews/
E2E_TIMEOUT_MS=240000
```

Покрытие включает auth/API, несколько организаций, scoped reviews, pagination, rating history, manual snapshots, parser monitoring, export XLSX/CSV/JSON, URL validation, DTO, fingerprint, fake parser persistence/deduplication, parser wrapper errors, E2E smoke и базовые Vue-компоненты.

## Production deploy

 Минимальная production-схема для хостинга:

- один HTTP entrypoint: Nginx/Traefik/Caddy -> Laravel public directory;
- PHP-FPM app container;
- отдельный queue worker `php artisan queue:work --tries=3 --timeout=190`;
- отдельный scheduler process `php artisan schedule:work` или cron `php artisan schedule:run` каждую минуту;
- отдельный parser service `npm --prefix parser run serve`;
- MySQL;
- Redis;
- HTTPS на внешнем домене.

В production наружу обычно открывается только HTTP/HTTPS. MySQL, Redis и parser port должны быть доступны только внутри private network.

Минимальные production env:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
FRONTEND_URL=https://your-domain.example
SANCTUM_STATEFUL_DOMAINS=your-domain.example

DB_HOST=your-mysql-host
DB_DATABASE=yandex_reviews
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host

YANDEX_PARSER_MODE=microservice
YANDEX_PARSER_SERVICE_URL=http://parser:3000
PARSER_QUEUE_DRIVER=redis
PARSER_REDIS_URL=redis://redis:6379
```

После деплоя:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Queue и scheduler должны быть под process manager-ом: Docker Compose restart policy, Supervisor, systemd или аналог.

## Типичные проблемы

### `Unauthenticated` после сохранения

Обычно это старые cookies Sanctum после изменения доменов. Решение:

1. Нажать `Выйти`.
2. Обновить страницу.
3. Войти снова.
4. Если не помогло, очистить cookies для `localhost` или открыть инкогнито.

### `localhost:8080` не открывается

Проверить контейнеры:

```bash
docker compose ps
docker compose logs --tail=100 app nginx
```

### Парсер долго в `Ожидает`

Проверить queue и parser:

```bash
docker compose logs -f queue parser
curl http://localhost:3000/health
```

### Порты заняты

В текущем compose внешние порты:

```text
8080 - приложение
5173 - Vite
3000 - parser
3307 - MySQL
6380 - Redis
```

Если они заняты, поменять левую часть mapping в `docker-compose.yml`.

## Ограничения

У Яндекс.Карт нет стабильного публичного API для получения всех отзывов карточки. Текущая реализация работает с динамической страницей и CSS/DOM-признаками, которые могут измениться. Антибот-защита может ограничить получение данных. Приложение не обходит капчу и не использует сомнительные способы обхода ограничений.

Что я бы доработал при большем времени:

- отдельный production parser service с observability;
- proxy/region strategy в рамках легального использования;
- richer retry policies;
- больше E2E-сценариев для ошибок parser-а и повторных запусков;
- отдельный экран администрирования parser queue.

## Безопасность

- Пароль хранится через hash.
- Login ограничен rate limiter-ом.
- Sanctum настроен для SPA-cookie auth.
- CORS поддерживает credentials только для разрешенных frontend origins.
- URL validation разрешает только HTTPS-ссылки Яндекс.Карт.
- Frontend не использует `v-html` для отзывов.
- Raw parser payload не отдается frontend-у.
