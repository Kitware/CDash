# syntax=docker/dockerfile:1

# Controls which base image is used to build the CDash image
# Options: "debian" or "ubi" (defaults to "debian")
ARG BASE_IMAGE=debian

# Designates as dev build, adds testing infrastructure, et al.
ARG DEVELOPMENT_BUILD

###############################################################################
# The base image for regular Debian-based images
###############################################################################
FROM php:8.3-apache-bookworm AS cdash-debian-intermediate

ARG BASE_IMAGE
ARG DEVELOPMENT_BUILD

RUN apt-get update && \
    apt-get install -y \
        ca-certificates \
        curl \
        gnupg \
        && \
    mkdir -p /etc/apt/keyrings && \
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
         | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
         | tee /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && \
    apt-get install -y \
        apt-utils \
        git \
        libbz2-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libldap2-dev \
        libmcrypt-dev \
        libpng-dev \
        libpq-dev \
        libxslt-dev \
        libxss1 \
        nodejs \
        unzip \
        vim \
        zip \
        && \
    docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql && \
    docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ && \
    docker-php-ext-install -j$(nproc) \
        bcmath \
        bz2 \
        gd \
        ldap \
        pdo_mysql \
        pdo_pgsql \
        xsl \
        opcache \
        && \
    curl -fsSL https://composer.github.io/installer.sha384sum > checksum && \
    curl -fsSL https://getcomposer.org/installer > composer-setup.php && \
    sha384sum -c checksum && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');" && \
    composer self-update --no-interaction

RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
    apt-get update && \
    apt-get install -y \
        cmake \
        rsync \
        libzip-dev `# needed for Laravel Dusk/Selenium` \
        && \
    `# Cypress dependencies` \
    apt-get install -y \
        libgtk2.0-0 \
        libgtk-3-0 \
        libgbm-dev \
        libnotify-dev \
        libgconf-2-4 \
        libnss3 \
        libxss1 \
        libasound2 \
        libxtst6 \
        xauth \
        xvfb \
        && \
    mkdir /tmp/.X11-unix && \
    chmod 1777 /tmp/.X11-unix && \
    chown root /tmp/.X11-unix/ && \
    mkdir -p /var/www/.cache/mesa_shader_cache && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    docker-php-ext-install zip; \
fi

# Create an npm cache directory for www-data
RUN mkdir -p /var/www/.npm && \
    chown -R www-data:www-data /var/www/.npm

# Copy Apache site-available config files into the image.
COPY ./docker/cdash-site.conf /etc/apache2/sites-available/cdash-site.conf

# Reconfigure Apache to only listen on port 8080.
RUN echo 'Listen 8080' > /etc/apache2/ports.conf

# Remove default site, add cdash-site, enable mod_rewrite, enable php
RUN a2dissite 000-default && \
    a2ensite cdash-site && \
    a2enmod rewrite && \
    a2enmod php && \
    a2enmod headers

# Enable https site if we're not doing a development build.
RUN if [ "$DEVELOPMENT_BUILD" != '1' ]; then \
    a2enmod ssl && \
    a2enmod socache_shmcb; \
fi

# Assign www-data ownership of apache2 configuration files
RUN chown -R www-data:www-data /etc/apache2

# Run the rest of the commands as www-data
USER www-data

# Copy CDash (current folder) into /cdash
COPY --chown=www-data:www-data . /cdash

WORKDIR /cdash

COPY ./php.ini /usr/local/etc/php/conf.d/cdash.ini

ENTRYPOINT ["/bin/bash", "/cdash/docker/docker-entrypoint.sh"]

###############################################################################
# The base image for UBI-based images
###############################################################################

FROM registry.access.redhat.com/ubi9/php-82 AS cdash-ubi-intermediate

ARG BASE_IMAGE
ARG DEVELOPMENT_BUILD

ENV TZ=UTC \
	LC_ALL=C.UTF-8 \
	LANG=C.UTF-8

USER 0

# Install Composer
RUN TEMPFILE=$(mktemp) && \
    curl -o "$TEMPFILE" "https://getcomposer.org/installer" && \
    php < "$TEMPFILE" && \
    mv composer.phar /usr/local/bin/composer

# install dependencies
RUN dnf install -y \
      --refresh \
      --best \
      --nodocs \
      --noplugins \
      --setopt=install_weak_deps=0 \
      #> helpers
      ca-certificates \
      findutils \
      shadow-utils \
      git \
      vim \
      unzip \
      zip \
      #> cdash
      php-bcmath \
      php-gd \
      php-ldap \
      php-mbstring \
      php-mysqlnd \
      php-pdo \
      php-opcache

RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
      dnf install -y \
          --refresh \
          --best \
          --nodocs \
          --noplugins \
          --setopt=install_weak_deps=0 \
          php-xdebug \
          rsync \
      #> A horrible hack to get a newer version of CMake.  As of the time of this
      #> writing, Red Hat UBI uses CMake 3.20, while our scripts require CMake>=3.22.
      #> This should be replaced with a more acceptable solution at a future point
      #> in time, whenever Red Had updates the default version of CMake.
          python-pip && \
      pip install cmake --upgrade && \
      dnf remove -y python-pip; \
    fi

# certs, timezone, accounts
RUN chmod -R g=u,o-w /etc/pki/ca-trust/extracted /etc/pki/ca-trust/source/anchors && \
	  update-ca-trust enable && \
	  update-ca-trust extract

# Allow PHP to access all environment variables.
# In the future, we may want to consider limiting this for security reasons.
RUN echo "clear_env = no" >> /etc/php-fpm.d/www.conf

USER 1001

# Copy CDash (current folder) into /cdash
COPY --chown=1001:1001 . /cdash

WORKDIR /cdash

COPY ./php.ini /etc/php.d/cdash.ini
COPY ./docker/cdash-site.conf /etc/httpd/conf.d/cdash-site.conf

# remove lcobucci/jwt due to libsodium rhel issue
RUN composer remove "lcobucci/jwt" --ignore-platform-reqs && rm -rf vendor

###############################################################################
# Do shared installation tasks as the root user
###############################################################################

FROM cdash-${BASE_IMAGE}-intermediate AS cdash-root-intermediate

ARG BASE_IMAGE
ARG DEVELOPMENT_BUILD

USER 0

RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
        echo "alias cdash_copy_source='rsync -r -l --exclude-from /cdash_src/.rsyncignore /cdash_src/ /cdash'" >> /etc/bash.bashrc; \
        echo "alias cdash_install='cdash_copy_source && bash /cdash/install.sh'" >> /etc/bash.bashrc; \
    else \
        echo "alias cdash_install='bash /cdash/install.sh'" >> /etc/bash.bashrc; \
    fi

# Disable git repo ownership check system wide
RUN git config --system --add safe.directory '*'

###############################################################################
# Intermediate images to switch the user back to the default non-root user
###############################################################################

FROM cdash-root-intermediate AS cdash-debian-non-root-intermediate
USER www-data

FROM cdash-root-intermediate AS cdash-ubi-non-root-intermediate
USER 1001

###############################################################################
# Do shared installation tasks as a non-root user
###############################################################################

FROM cdash-${BASE_IMAGE}-non-root-intermediate AS cdash

LABEL MAINTAINER="Kitware, Inc. <cdash@public.kitware.com>"

ARG BASE_IMAGE
ARG DEVELOPMENT_BUILD

ENV CYPRESS_CACHE_FOLDER=/cdash/cypress_cache

# Set up testing environment if this is a development build
RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
        mkdir _build && cd _build && \
        cmake \
            -DCDASH_DIR_NAME= \
            -DCDASH_SERVER=localhost:8080 \
            -DCTEST_UPDATE_VERSION_ONLY=1 ..; \
    fi

# Install dependencies, including dev dependencies if this is a development build
RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
        composer install --no-interaction --no-progress --prefer-dist \
        && npm install; \
    else \
        composer install \
            --no-interaction \
            --no-progress  \
            --prefer-dist  \
            --no-dev \
            --optimize-autoloader && \
        npm install --omit=dev; \
    fi

# In development, we install the development .env by default
# This could be switched to regular environment variables inserted via docker compose in the future.
RUN if [ "$DEVELOPMENT_BUILD" = '1' ]; then \
        cp /cdash/.env.dev /cdash/.env; \
    fi

RUN npm run prod --stats-children

# Make sure the build args are set in the ENV for reference in docker-entrypoint.sh
ENV DEVELOPMENT_BUILD=$DEVELOPMENT_BUILD
ENV BASE_IMAGE=$BASE_IMAGE

ENTRYPOINT ["/bin/bash", "/cdash/docker/docker-entrypoint.sh"]
CMD ["start-website"]

