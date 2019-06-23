FROM php:7.0-apache

LABEL maintainer="Kitware, Inc. <cdash@public.kitware.com>"

RUN apt-get update                                                             \
 && apt-get install -y gnupg                                                   \
 && curl -sL https://deb.nodesource.com/setup_6.x | bash                       \
 && apt-get install -y git libbz2-dev libfreetype6-dev libjpeg62-turbo-dev     \
    libmcrypt-dev libpng-dev libpq-dev libxslt-dev libxss1 nodejs unzip wget   \
    zip                                                                        \
 && docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql               \
 && docker-php-ext-configure gd --with-freetype-dir=/usr/include/              \
                                --with-jpeg-dir=/usr/include/                  \
 && docker-php-ext-install -j$(nproc) bcmath bz2 gd pdo_mysql pdo_pgsql xsl    \
 && wget -q -O checksum https://composer.github.io/installer.sha384sum         \
 && wget -q -O composer-setup.php https://getcomposer.org/installer            \
 && sha384sum -c checksum                                                      \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer    \
 && php -r "unlink('composer-setup.php');"                                     \
 && composer self-update --no-interaction

WORKDIR /home/kitware/cdash
COPY php.ini /usr/local/etc/php/conf.d/cdash.ini
COPY xml_handlers /home/kitware/cdash/xml_handlers
COPY app /home/kitware/cdash/app
COPY composer.lock /home/kitware/cdash/composer.lock
COPY public /home/kitware/cdash/public
COPY scripts /home/kitware/cdash/scripts
COPY sql /home/kitware/cdash/sql
COPY package.json /home/kitware/cdash/package.json
COPY .php_cs /home/kitware/cdash/.php_cs
COPY config /home/kitware/cdash/config
COPY log /home/kitware/cdash/log
COPY gulpfile.js /home/kitware/cdash/gulpfile.js
COPY backup /home/kitware/cdash/backup
COPY include /home/kitware/cdash/include
COPY bootstrap /home/kitware/cdash/bootstrap
COPY composer.json /home/kitware/cdash/composer.json
COPY scripts/bash /bash-lib
COPY docker/cdash-site.conf /etc/apache2/sites-available/cdash-site.conf

RUN composer install --no-interaction --no-progress --prefer-dist --no-dev \
 && npm install                                                            \
 && node_modules/.bin/gulp                                                 \
 && chmod 777 backup log public/rss public/upload

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Remove default site, add cdash-site, enable php7
RUN a2dissite 000-default  \
 && a2ensite cdash-site    \
 && a2enmod php7

WORKDIR /home/kitware/cdash
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=5m \
  CMD ["curl", "-f", "http://localhost/viewProjects.php"]

ENTRYPOINT ["/bin/bash", "/docker-entrypoint.sh"]
CMD ["serve"]
