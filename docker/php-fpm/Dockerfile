FROM php:7.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends
RUN pecl install imagick
RUN docker-php-ext-enable imagick

RUN docker-php-ext-install opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install exif

COPY config/general.ini $PHP_INI_DIR/conf.d/
COPY config/opcache.ini $PHP_INI_DIR/conf.d/

RUN mkdir -p /var/data

CMD ["php-fpm"]

EXPOSE 9000
