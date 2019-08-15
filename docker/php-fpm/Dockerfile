FROM php:7.2-fpm-alpine

WORKDIR /var/www

RUN docker-php-ext-install opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install exif

COPY config/opcache.ini $PHP_INI_DIR/conf.d/

RUN mkdir -p /var/data

CMD ["php-fpm"]

EXPOSE 9000
