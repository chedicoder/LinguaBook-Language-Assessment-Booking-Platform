FROM php:8.5-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

FROM base AS development
ENV APP_ENV=dev
EXPOSE 8000

FROM base AS dependencies
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM php:8.5-apache AS production
RUN apt-get update \
    && apt-get install -y --no-install-recommends libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=dependencies /app /var/www/html
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p var && chown -R www-data:www-data var
ENV APP_ENV=prod
EXPOSE 80
