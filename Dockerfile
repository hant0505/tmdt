FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    libonig-dev \
    unzip \
    git \
    curl

RUN docker-php-ext-install \
    pdo_mysql \
    soap \
    bcmath \
    sockets \
    zip \
    intl \
    xsl \
    opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
