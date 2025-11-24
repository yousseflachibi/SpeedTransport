# ./docker/php/Dockerfile
FROM php:8.0-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    libonig-dev \
    default-mysql-client \
    git \
    ca-certificates \
    unzip \
    curl \
 && docker-php-ext-configure zip \
 && docker-php-ext-install pdo_mysql zip \
 && update-ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# APCu (optionnel)
RUN pecl update-channels && pecl install apcu && docker-php-ext-enable apcu || true

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /usr/src/app

# copie de l'application (ou monte en volume en dev)
COPY apps/my-symfony-app /usr/src/app

ENV PATH="${PATH}:/usr/src/app/vendor/bin:bin"