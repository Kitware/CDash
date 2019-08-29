#!/usr/bin/env bash

cdash_environment() {
  local environment
  local database

  environment="$1"; shift

  if [ "$environment" '=' 'production' ] ; then
    echo "Creating production environment..."
  fi

  if [ "$environment" '!=' 'production' ] ; then
    CDASH_DATABASE=${environment^^}
  fi

  echo "Setting enviroment variables..."
  export CDASH_DATABASE
  echo "CDASH_DATABASE=${CDASH_DATABASE}"

  echo "Linking compose file docker/docker-compose.${environment}.yml ..."
  ln -fs "./docker/docker-compose.${environment}.yml" ./docker-compose.local.yml
}

cdash_build_image() {
  docker-compose -f ./docker-compose.local.yml build --force-rm --no-cache cdash
}

cdash_start_docker_services() {
  docker-compose -f ./docker-compose.local.yml up -d
}

cdash_wait_for_ready() {
  local url
  local allowed
  local attemts
  local wait_seconds
  local ok

  url="$1" ; shift
  allowed="$1"  ; shift
  attempts=0
  wait_seconds=5

  until ok=$(docker exec cdash curl -f --silent --show-error  "$url") || [[ ${attempts} -ge ${allowed} ]]; do
    >&2 echo "CDash not ready, waiting ${wait_seconds}s..."
    attempts=$((attempts+1))
    sleep ${wait_seconds}
  done

  if [[ ${attempts} -ge ${allowed} ]]; then
    >&2 echo "Aborting: maximum attempts to connect reached"
    return 1
  fi
}

cdash_run_and_submit_ctest() {
  local postgres
  local ctest_driver

  ctest_driver="/home/kitware/cdash/.circleci/ctest_driver_script.cmake"

  if [ "$CDASH_DATABASE" '=' 'POSTGRES' ] ; then
    postgres='ON'
  else
    postgres='OFF'
  fi
  docker exec cdash bash -c "cd /home/kitware/cdash && /usr/bin/git checkout ."
  docker exec --user www-data cdash bash -c "/usr/bin/ctest -VV -DBUILDNAME=\"${CIRCLE_BRANCH}_${CDASH_DATABASE}\" -Dpostgres=${postgres} -S ${ctest_driver}"
}
