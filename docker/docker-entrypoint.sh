#!/bin/bash

set -e

# If no arguments, or if the "start-website" argument was provided, start the web server
if [ $# -eq 0 ] || [ "$1" = "start-website" ] ; then
  bash /cdash/install.sh
  /usr/sbin/apache2ctl -D FOREGROUND

# If the start-worker argument was provided, start a worker process instead
elif [ "$1" = "start-worker" ] ; then
  php artisan queue:work

# Otherwise, throw an error...
else
  echo "Unknown argument(s) provided: $*"
  echo "Use 'start-website' to start the CDash website, or 'start-worker' to start a worker process."
  exit 1
fi
