FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libxslt1-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    msmtp \
    msmtp-mta \
    ca-certificates \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        soap \
        bcmath \
        sockets \
        zip \
        intl \
        xsl \
        ftp \
        gd \
        opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/msmtprc.template /opt/docker/msmtprc.template
COPY docker/php-entrypoint.sh /usr/local/bin/php-entrypoint

RUN chmod 755 /usr/local/bin/php-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["php-entrypoint"]
CMD ["php-fpm"]
