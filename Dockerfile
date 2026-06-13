FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev default-mysql-client nodejs npm $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install intl pdo_mysql zip \
    && groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && npm install \
    && npm --prefix parser install \
    && cd parser \
    && npx playwright install --with-deps chromium \
    && cd .. \
    && npm run build

CMD ["php-fpm"]
