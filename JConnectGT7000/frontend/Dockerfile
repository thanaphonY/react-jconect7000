FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pgsql \
        pdo_pgsql \
        gd \
        zip \
        mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/frontend
