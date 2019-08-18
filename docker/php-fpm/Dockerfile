FROM php:7.2-fpm

WORKDIR /var/www/web

RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends
RUN pecl install imagick
RUN docker-php-ext-enable imagick

# TODO: install imagic with heic support?
# https://medium.com/@eplt/5-minutes-to-install-imagemagick-with-heic-support-on-ubuntu-18-04-digitalocean-fe2d09dcef1

RUN docker-php-ext-install opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install exif

COPY config/general.ini $PHP_INI_DIR/conf.d/
COPY config/opcache.ini $PHP_INI_DIR/conf.d/

EXPOSE 9000

CMD ["php-fpm"]
