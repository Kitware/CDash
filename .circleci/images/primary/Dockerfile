FROM kitware/cdash:latest

LABEL maintainer="Kitware, Inc. <cdash@public.kitware.com>"

LABEL com.circleci.preserve-entrypoint=true

ARG CDASH_DATABASE_HOST=localhost

# Creates the kitware home directory with directory for cdash
WORKDIR /var/www/cdash

# Copy CDash (current folder) into /home/kitware/cdash to set its owner
# to www-data
ADD --chown=www-data:www-data . ./

WORKDIR /var/www/cdash

RUN apt-get install -y cmake                                                \
 && pecl install xdebug                                                     \
 && docker-php-ext-enable xdebug                                            \
 && cp app/cdash/php.ini /usr/local/etc/php/conf.d/cdash.ini                \
 && mkdir _build && cd _build                                               \
 # CMake runs composer, npm and gulp during the configure step.
 && cmake                                                                   \
  -DCDASH_DB_HOST=$CDASH_DATABASE_HOST                                      \
  -DCDASH_DIR_NAME=                                                         \
  -DCDASH_TESTING_URL='http://localhost/'                                   \
  -DCDASH_SERVER='localhost'                                                \
  ..

# Configure Laravel
# copy the docker specific environment file from the docker directory
RUN cp docker/.env.docker .env                                              \
 && php artisan key:generate                                                \
 # copy the docker specific protractor configuration file for js testing
 && cp docker/conf.js _build/app/cdash/tests/js/e2e_tests/conf.js           \
 && chown -R www-data:www-data .

# Configure Apache
RUN cp /var/www/cdash/docker/cdash-site.conf /etc/apache2/sites-available/cdash-site.conf
# Remove default site, add cdash-site, enable mod_rewrite, enable php7
RUN a2dissite 000-default  \
 && a2ensite cdash-site    \
 && a2enmod rewrite        \
 && a2enmod php7

EXPOSE 80

ENTRYPOINT ["ctest", "/var/www/cdash/_build"]
