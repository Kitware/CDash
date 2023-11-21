#!/bin/bash

set -Eeuo pipefail
echo "=================================================================================";
echo "Beginning CDash installation...";

INSTALLATION_ERROR_MESSAGE="
* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
*                             INSTALLATION FAILURE!                             *
*                                                                               *
* An error occurred while upgrading your CDash installation and CDash was not   *
* able to recover automatically.  If you believe that this is a problem with    *
* CDash, please report the error here:                                          *
*                                                                               *
* -> https://github.com/Kitware/CDash/issues/new                                *
*                                                                               *
* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
";

# Temporarily change to /cdash
{
  pushd "/cdash" > /dev/null &&

  echo "Enabling maintenance mode..." &&
  php artisan down --render="maintenance" --refresh=5 &&

  echo "Updating vendor dependencies..." &&
  npm install &&
  composer install &&

  echo "Running migrations..." &&
  php artisan migrate &&
  php artisan version:set &&

  echo "Clearing caches..." &&
  php artisan route:cache &&
  php artisan view:cache &&

  echo "Building the website..." &&
  npm run prod --stats-children &&

  echo "Bringing CDash back online..." &&
  php artisan up &&

  popd > /dev/null
} || { echo "${INSTALLATION_ERROR_MESSAGE}"; exit $?; }

echo "done."
