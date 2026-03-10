FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libxslt1-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install \
        pdo_mysql \
        soap \
        bcmath \
        sockets \
        zip \
        intl \
        xsl \
        opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
