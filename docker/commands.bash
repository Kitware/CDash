#!/usr/bin/env bash

cdash_environment() {
  local environment

  environment="$1"; shift

  echo "Linking compose file docker/docker-compose.${environment}.yml ..."
  ln -fs "./docker/docker-compose.${environment}.yml" ./docker-compose.local.yml
}

cdash_build_image() {
  docker-compose -f ./docker-compose.local.yml build --force-rm --no-cache cdash
}

cdash_start_docker_services() {
  docker-compose -f ./docker-compose.local.yml up -d
}

cdash_stop_docker_services() {
  docker-compose down --remove-orphans
}

cdash_wait_for_ready() {
  local url
  local allowed
  local attempts
  local wait_seconds
  local ok

  url="$1" ; shift
  allowed="$1"  ; shift
  attempts=0
  wait_seconds=5

  if [[ ${attempts} -ge ${allowed} ]]; then
    >&2 echo "Aborting: maximum attempts to connect reached"
    return 1
  fi

  until ok=$(docker exec cdash curl -f --silent --show-error  "$url") || [[ ${attempts} -ge ${allowed} ]]; do
    >&2 echo "CDash not ready, waiting ${wait_seconds}s..."
    attempts=$((attempts+1))
    sleep ${wait_seconds}
  done

  >&2 echo "CDash is ready!"
}

cdash_site() {
  local host=$(hostname)
  local site="${SITENAME:-$host}"
  echo "$site"
}

cdash_branch() {
  local branch=$(git rev-parse --abbrev-ref HEAD)
  echo "$branch"
}

cdash_run_and_submit_ctest() {
  local database
  local postgres
  local ctest_driver
  local site
  local branch

  ctest_driver="/home/kitware/cdash/.circleci/ctest_driver_script.cmake"

  database="$1" ; shift
  postgres="$1" ; shift

  site=$(cdash_site)
  branch=$(cdash_branch)

  docker exec cdash bash -c "cd /home/kitware/cdash && /usr/bin/git checkout ."

  echo "site=$site"
  echo "branch=$branch"
  echo "database=$database"
  echo "postgres=$postgres"
  echo "ctest_driver=$ctest_driver"

  docker exec --user www-data cdash bash -c "/usr/bin/ctest -VV -DSITENAME=\"${site}\" -DBUILDNAME=\"${branch}_${database}\" -Dpostgres=${postgres} -S ${ctest_driver}"
}

cdash_run_and_submit_mysql_ctest() {
  cdash_run_and_submit_ctest MySQL OFF
}

cdash_run_and_submit_pgsql_ctest() {
  cdash_run_and_submit_ctest PgSQL ON
}
