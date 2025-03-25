#!/bin/bash

set -e

# Set job control so we can bg/fg processes
set -m

php artisan key:check || exit 1

# If the "start-website" argument was provided, start the web server
if [ "$1" = "start-website" ] ; then
  if [ "$DEVELOPMENT_BUILD" = "1" ]; then
    echo "Skipping background jobs in development mode..."
  else
    echo "Starting background jobs..."
    # Output will show up in the logs, but the system will not crash if the schedule process fails.
    # In the future, it would be better to do this with a dedicated container which starts on a schedule.
    # Bare metal systems should use cron instead (using cron in Docker is problematic).
    php artisan schedule:work & # & puts the task in the background.
  fi

  echo "Starting Apache..."

  # Start Apache under the current user, in case the current user isn't www-data.  Kubernetes-based systems
  # typically run under a random user.  We start Apache before running the install scripts so the system can
  # begin collecting submissions while database migrations run.  Apache starts in the background so the
  # container gets killed if the migrations fail.
  if [ "$BASE_IMAGE" = "debian" ] ; then
    APACHE_RUN_USER=$(id -u -n) /usr/sbin/apache2ctl -D FOREGROUND
  elif [ "$BASE_IMAGE" = "ubi" ]; then
    /usr/libexec/s2i/run
  fi & # & puts Apache in the background

  if [ "$DEVELOPMENT_BUILD" = "1" ]; then
    bash /cdash/install.sh --dev --initial-docker-install
  else
    bash /cdash/install.sh --initial-docker-install
  fi

  # Bring Apache to the foreground so the container fails if Apache fails after this point.
  fg

# If the start-worker argument was provided, start a worker process instead
elif [ "$1" = "start-worker" ] ; then
  php artisan storage:mkdirs
  php -d memory_limit=-1 artisan queue:work

# Otherwise, throw an error...
else
  echo "Unknown argument(s) provided: $*"
  echo "Use 'start-website' to start the CDash website, or 'start-worker' to start a worker process."
  exit 1
fi
