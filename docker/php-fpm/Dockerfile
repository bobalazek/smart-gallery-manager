FROM php:7.2-fpm

RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install opcache

CMD ["php-fpm"]

EXPOSE 9000
