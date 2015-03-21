FROM        unsee/base
MAINTAINER  Mike Gorianskyi goreanski@gmail.com

RUN         apt-get update && apt-get install -y \
                php5-fpm \
                php-pear \
                php5-dev \
                php5-imagick \
                imagemagick

RUN         pecl install redis

EXPOSE      9000
CMD         php5-fpm -F

RUN         unlink /etc/php5/fpm/pool.d/www.conf
ADD         scripts/php-fpm-pool.conf /etc/php5/fpm/pool.d/unsee.conf
ADD         scripts/unsee.ini /etc/php5/fpm/conf.d/unsee.ini

RUN         mkdir -p /var/www/unsee/

ADD         application/ /var/www/unsee/application
ADD         library/ /var/www/unsee/library
ADD         public/ /var/www/unsee/public

RUN         cd /var/www/unsee/ && curl -sS https://getcomposer.org/installer | php
RUN         cd /var/www/unsee/ && php composer.phar require zendframework/zendframework1
