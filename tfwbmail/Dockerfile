FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libkrb5-dev \
        libc-client-dev \
        libonig-dev \
        libssl-dev \
        libsqlite3-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j"$(nproc)" imap mbstring pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

EXPOSE 80
