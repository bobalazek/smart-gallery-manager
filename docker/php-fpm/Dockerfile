FROM php:7.2-fpm

WORKDIR /var/www/web

RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends
RUN pecl install imagick
RUN docker-php-ext-enable imagick

RUN apt-get install curl wget ufraw -y

RUN docker-php-ext-install opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install exif

COPY config/general.ini $PHP_INI_DIR/conf.d/
COPY config/opcache.ini $PHP_INI_DIR/conf.d/
COPY config/www.conf /usr/local/etc/php-fpm.d/

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/ba13e3fc70f1c66250d1ea7ea4911d593aa1dba5/web/installer -O - -q | php -- --quiet

EXPOSE 9000

CMD ["php-fpm"]
