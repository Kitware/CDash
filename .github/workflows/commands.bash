#!/usr/bin/env bash

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

  ctest_driver="/cdash/.github/workflows/ctest_driver_script.cmake"

  database="$1" ; shift
  postgres="$1" ; shift

  site=$(cdash_site)
  branch=$(cdash_branch)

  docker exec --user www-data cdash bash -c "cd /cdash && /usr/bin/git checkout ."

  echo "site=$site"
  echo "branch=$branch"
  echo "database=$database"
  echo "postgres=$postgres"
  echo "ctest_driver=$ctest_driver"

  docker exec --user www-data cdash bash -c "/usr/bin/ctest -VV -j 4 --schedule-random -DSITENAME=\"${site}\" -DBUILDNAME=\"${branch}_${database}\" -Dpostgres=${postgres} -S ${ctest_driver}"
}

cdash_run_and_submit_mysql_ctest() {
  cdash_run_and_submit_ctest MySQL OFF
}

cdash_run_and_submit_postgres_ctest() {
  cdash_run_and_submit_ctest PgSQL ON
}
