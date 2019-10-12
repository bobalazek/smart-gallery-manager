FROM php:7.2-fpm

WORKDIR /var/www/web

RUN apt-get update && apt-get install -y
RUN apt-get install git curl wget -y

# Imagick
RUN apt-get install ufraw libmagickwand-dev -y
RUN pecl install imagick
RUN docker-php-ext-enable imagick

# Opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install opcache

# Zip
RUN apt-get install zip unzip libzip-dev -y
RUN docker-php-ext-configure zip --with-libzip
RUN docker-php-ext-install zip

# Exif
RUN docker-php-ext-install exif

# PDO Mysql
RUN docker-php-ext-install pdo_mysql

# Redis
RUN pecl install redis
RUN docker-php-ext-enable redis

# Memcached
RUN apt-get install memcached -y
RUN apt-get install -y libmemcached-dev zlib1g-dev libicu-dev
RUN git clone -b php7 https://github.com/php-memcached-dev/php-memcached /usr/src/php/ext/memcached \
  && docker-php-ext-configure /usr/src/php/ext/memcached --disable-memcached-sasl \
  && docker-php-ext-install /usr/src/php/ext/memcached \
  && rm -rf /usr/src/php/ext/memcached

# Config
COPY config/general.ini $PHP_INI_DIR/conf.d/
COPY config/opcache.ini $PHP_INI_DIR/conf.d/
COPY config/www.conf /usr/local/etc/php-fpm.d/

# Supervisor
RUN apt-get install supervisor -y
COPY supervisor/supervisord.conf /etc/supervisor/conf.d/

# Composer
RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/ba13e3fc70f1c66250d1ea7ea4911d593aa1dba5/web/installer -O - -q | php -- --quiet
RUN mv composer.phar /usr/local/bin/composer

# Permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

EXPOSE 9000

CMD ["/usr/bin/supervisord"]
