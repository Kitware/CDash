#!/bin/bash

DEVELOPMENT=false
INITIAL_DOCKER_INSTALL=false

for ARG in "$@"; do
  shift
  if [[ "$ARG" == "--dev" ]]; then
    DEVELOPMENT=true
  fi
  if [[ "$ARG" == "--initial-docker-install" ]]; then
    INITIAL_DOCKER_INSTALL=true
  fi
done

echo "=================================================================================";
if $DEVELOPMENT; then
  echo "Beginning development CDash installation..."
else
  echo "Beginning production CDash installation..."
fi

error_handler() {
echo "
* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
*                            INSTALLATION FAILURE!                            *
*                                                                             *
* An error occurred while installing CDash and CDash was not able to recover  *
* automatically.  If you believe that this is a problem with CDash, please    *
* report the error here:                                                      *
*                                                                             *
* -> https://github.com/Kitware/CDash/issues/new                              *
*                                                                             *
* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
"

exit 1
}

trap error_handler ERR

# Temporarily change to the root of the CDash source tree.
SCRIPT_DIR=$(dirname "$0")
pushd "$SCRIPT_DIR" > /dev/null

echo "Enabling maintenance mode..."
php artisan down --render="maintenance" --refresh=5

if $INITIAL_DOCKER_INSTALL; then
  echo "Skipping vendor installation..."
else
  echo "Updating vendor dependencies..."
    if $DEVELOPMENT; then
      npm install
      composer install
    else
      npm install --omit=dev
      composer install --no-dev --optimize-autoloader
    fi
fi

echo "Creating storage directories..."
php artisan storage:mkdirs

echo "Waiting for database to come online..."
until php artisan db:monitor ; do sleep 1; done

echo "Running migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan view:cache
php artisan lighthouse:cache

if $INITIAL_DOCKER_INSTALL; then
  echo "Skipping website build..."
else
  echo "Building the website..."
    npm run prod --stats-children
fi

echo "Bringing CDash back online..."
php artisan up

popd > /dev/null

echo "done."
