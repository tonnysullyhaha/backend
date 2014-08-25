FROM	debian:7.4
MAINTAINER Mike Gorianskyi goreanski@gmail.com
ENV		HOME /root
ENV		DEBIAN_FRONTEND noninteractive

RUN		apt-get update && apt-get install -y apt-utils wget

# Added dotdeb to apt
RUN     echo "deb http://packages.dotdeb.org wheezy-php55 all" >> /etc/apt/sources.list.d/dotdeb.org.list && \
        echo "deb-src http://packages.dotdeb.org wheezy-php55 all" >> /etc/apt/sources.list.d/dotdeb.org.list && \
        wget -O- http://www.dotdeb.org/dotdeb.gpg | apt-key add -

RUN		apt-get update && apt-get install -y \
        zip \
        libpcre3-dev \
        libgeoip-dev \
        libssl-dev \
        build-essential \
        php5-fpm \
        php-pear \
        php5-dev \
        php5-imagick \ 
        imagemagick \
        vim

RUN     pecl install redis
RUN     echo 'extension=redis.so' > /etc/php5/fpm/conf.d/redis.ini

EXPOSE  9000
CMD     php5-fpm -F

RUN     unlink /etc/php5/fpm/pool.d/www.conf
ADD     scripts/php-fpm-pool.conf /etc/php5/fpm/pool.d/unsee.conf