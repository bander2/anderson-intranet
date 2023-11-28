FROM php:8.2-apache

ENV PATH="${PATH}:/var/www/drupal/vendor/bin"

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    bcmath \
    gd \
    pdo_mysql \
    intl \
    apcu \
    zip \
    opcache \
    imap \
    uploadprogress \
    @composer \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    curl \
    wget \
    default-mysql-client \
    openssl \
    patch \
    && apt-get clean

RUN mkdir -p /var/www/drupal/files /var/www/drupal/web/sites/default/files

COPY 000-default.conf /etc/apache2/sites-enabled/

COPY drupal/composer.json                   /var/www/drupal/composer.json
COPY drupal/composer.lock                   /var/www/drupal/composer.lock
COPY drupal/config                          /var/www/drupal/config
COPY drupal/web/modules/custom              /var/www/drupal/web/modules/custom
COPY drupal/web/sites/default/settings.php  /var/www/drupal/web/sites/default/settings.php

WORKDIR /var/www/drupal

RUN composer install --no-dev

VOLUME /var/www/drupal
