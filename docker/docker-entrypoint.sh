#!/bin/bash

set -e

php artisan key:check || exit 1

# If the "start-website" argument was provided, start the web server
if [ "$1" = "start-website" ] ; then
  if [ "$DEVELOPMENT_BUILD" = "1" ]; then
    bash /cdash/install.sh --dev
  else
    bash /cdash/install.sh
  fi

  echo "Starting Apache..."

  # Start Apache under the current user, in case the current user isn't www-data.  Kubernetes-based systems
  # typically run under a random user.
  APACHE_RUN_USER=$(id -u -n) /usr/sbin/apache2ctl -D FOREGROUND

# If the start-worker argument was provided, start a worker process instead
elif [ "$1" = "start-worker" ] ; then
  php artisan queue:work

# Otherwise, throw an error...
else
  echo "Unknown argument(s) provided: $*"
  echo "Use 'start-website' to start the CDash website, or 'start-worker' to start a worker process."
  exit 1
fi
