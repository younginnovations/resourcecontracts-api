FROM ubuntu:14.04
MAINTAINER Anjesh Tuladhar <anjesh@yipl.com.np>

RUN apt-get update && apt-get install -y \
    curl \
    git \
    wget \
    apache2 \
    php5 \
    php5-cli \
    php5-curl \
    php5-mcrypt \
    php5-readline \
    supervisor \    
    gettext \    
 && rm -rf /var/lib/apt/lists/* \
 && curl -O -L https://github.com/papertrail/remote_syslog2/releases/download/v0.19/remote_syslog_linux_amd64.tar.gz \
 && tar -zxf remote_syslog_linux_amd64.tar.gz \
 && cp remote_syslog/remote_syslog /usr/local/bin \
 && rm -r remote_syslog_linux_amd64.tar.gz \
 && rm -r remote_syslog

COPY conf/rc-api.conf /etc/apache2/sites-available/rc-api.conf
RUN ln -s /etc/apache2/sites-available/rc-api.conf /etc/apache2/sites-enabled/rc-api.conf \
 && rm -f /etc/apache2/sites-enabled/000-default.conf

RUN a2enmod rewrite \
 && a2enmod headers \
 && a2enmod php5 \
 && ln -s /etc/php5/mods-available/mcrypt.ini /etc/php5/apache2/conf.d/20-mcrypt.ini \
 && ln -s /etc/php5/mods-available/mcrypt.ini /etc/php5/cli/conf.d/20-mcrypt.ini \
 && mkdir -p /var/container_init \
 && mkdir -p /var/www/rc-api \
 && mkdir -p /var/log/supervisor

WORKDIR /var/www/rc-api

COPY conf/init.sh /var/container_init/init.sh
COPY conf/env.template /var/container_init/env.template
COPY conf/log_files.yml.template /var/container_init/log_files.yml.template
COPY composer.json /var/www/rc-api
COPY composer.lock /var/www/rc-api
COPY conf/supervisord.conf /etc/supervisord.conf
RUN curl -s http://getcomposer.org/installer | php \
 && php composer.phar install --prefer-dist --no-scripts --no-autoloader

COPY . /var/www/rc-api

RUN php composer.phar dump-autoload --optimize \
 && chown -R www-data: /var/www/rc-api

EXPOSE 80
CMD cd /var/container_init && ./init.sh && /usr/bin/supervisord -c /etc/supervisord.conf && /usr/sbin/apache2ctl -D FOREGROUND

