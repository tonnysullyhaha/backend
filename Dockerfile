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
ADD         * /var/www/unsee/
