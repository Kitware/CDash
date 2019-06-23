#!/usr/bin/env bash
# circleci-wait.bash

set -e

host="$1"
allowed="$2"
attempts=0
wait_seconds=5

until docker exec cdash curl -f --silent --show-error  "$host" || [[ ${attempts} -ge ${allowed} ]]; do
  >&2 echo "CDash not ready, waiting ${wait_seconds}s..."
  attempts=$((attempts+1))
  sleep ${wait_seconds}
done

if [[ ${attempts} -ge ${allowed} ]]; then
>&2 echo "Maximum attempts to connect reached"
exit 1
fi
