FROM node:8.16.1-stretch-slim AS js-dependencies
WORKDIR /home/kitware/cdash
COPY ./package-lock.json .
COPY ./package.json .
RUN npm install

FROM composer:latest AS php-dependencies
WORKDIR /home/kitware/cdash
RUN mkdir -p database/seeds database/factories
COPY ./composer.lock .
COPY ./composer.json .
RUN composer install --no-interaction --no-progress --prefer-dist --no-dev        \
                     --ignore-platform-reqs --no-scripts

FROM php:7.2-apache-stretch AS CDASH
WORKDIR /home/kitware/cdash
RUN mkdir vendor node_modules
RUN apt-get update                                                                \
 && apt-get install -y gnupg git libbz2-dev libfreetype6-dev libjpeg62-turbo-dev  \
            libmcrypt-dev libpng-dev libpq-dev libxslt-dev libxss1 nodejs unzip   \
            wget zip                                                              \
 && docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql                  \
 && docker-php-ext-configure gd --with-freetype-dir=/usr/include/                 \
                                --with-jpeg-dir=/usr/include/                     \
 && docker-php-ext-install -j$(nproc) bcmath bz2 gd pdo_mysql pdo_pgsql xsl

COPY --from=js-dependencies /usr/local/bin/node /usr/local/bin/
COPY --from=js-dependencies /home/kitware/cdash/node_modules ./node_modules
COPY --from=php-dependencies /home/kitware/cdash/vendor ./vendor
COPY ./app ./app
COPY ./artisan ./artisan
COPY ./bootstrap ./bootstrap
COPY ./.circleci ./.circleci
COPY ./CMakeLists.txt ./CMakeLists.txt
COPY ./composer.json ./composer.json
COPY ./composer.lock ./composer.lock
COPY ./config ./config
COPY ./CTestConfig.cmake ./CTestConfig.cmake
COPY ./CTestCustom.cmake.in ./CTestCustom.cmake.in
COPY ./database ./database
COPY ./docker-compose.yml ./docker-compose.yml
COPY ./docker ./docker
COPY ./docker/docker-entrypoint.sh /docker-entrypoint.sh
COPY ./docker/bash /bash-lib
COPY ./.dockerignore ./.dockerignore
COPY ./.editorconfig  ./.editorconfig
COPY ./.env.example ./.env
COPY ./.env.example ./.env.example
COPY ./.eslintrc ./.eslintrc
COPY ./.gitattributes ./.gitattributes
COPY ./.git ./.git
COPY ./.gitignore ./.gitignore
COPY ./gulpfile.js ./gulpfile.js
COPY ./package.json ./package.json
COPY ./package-lock.json ./package-lock.json
COPY ./.php_cs ./.php_cs
COPY ./phpunit.xml ./phpunit.xml
COPY ./public ./public
COPY ./README.md ./README.md
COPY ./resources ./resources
COPY ./routes ./routes
COPY ./server.php ./server.php
COPY ./storage ./storage
COPY ./.styleci.yml ./.styleci.yml
COPY ./tests ./tests
COPY ./webpack.mix.js ./webpack.mix.js
COPY ./docker/cdash-site.conf /etc/apache2/sites-available/cdash-site.conf

RUN cp app/cdash/php.ini /usr/local/etc/php/conf.d/cdash.ini
RUN node_modules/.bin/gulp
RUN php artisan key:generate
RUN chown -R www-data:www-data .
RUN a2dissite 000-default && a2ensite cdash-site && a2enmod rewrite && a2enmod php7
RUN chmod +x /docker-entrypoint.sh
ENTRYPOINT ["/bin/bash", "/docker-entrypoint.sh"]
CMD ["serve"]

