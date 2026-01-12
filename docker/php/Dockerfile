FROM php:8.3-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
  && docker-php-ext-install pdo_mysql \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd \
  && a2enmod rewrite headers \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

