FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev default-mysql-client nodejs npm \
    && docker-php-ext-install intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && npm install \
    && npm --prefix parser install \
    && npm run build

CMD ["php-fpm"]
