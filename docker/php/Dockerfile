FROM php:8.3-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libzip-dev \
  && docker-php-ext-install pdo_mysql \
  && a2enmod rewrite headers \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

