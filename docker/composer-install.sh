#!/bin/sh

mkdir -p vendor/bin vendor/composer
EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'vendor/composer/composer-setup.php');"
ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'vendor/composer/composer-setup.php');")"

if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
then
    >&2 echo 'ERROR: Invalid installer signature'
    rm vendor/composer/composer-setup.php
    exit 1
fi

php vendor/composer/composer-setup.php --install-dir=vendor/bin --filename=composer

RESULT=$?
rm vendor/composer/composer-setup.php
exit $RESULT
