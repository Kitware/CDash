#!/usr/bin/env bash

ctest_driver="/cdash/.github/workflows/ctest_driver_script.cmake"

submit_type="$1"
submit_type="${submit_type:-Experimental}"

site="${SITENAME:-$(hostname)}"

storage_type="${STORAGE_TYPE:-local}"

postgres_version="${POSTGRES_VERSION:-18}"

echo "site=$site"
echo "ctest_driver=$ctest_driver"
echo "submit_type=$submit_type"

# Wait a couple seconds for the migrations to start running
sleep 2

# Wait for migrations to finish running by checking for maintenance mode to be lifted
docker exec cdash-website-1 bash -c "\
  until [ ! -f /cdash/storage/framework/down ]; \
  do \
    sleep 1; \
  done \
"

# Suppress any uncommitted changes left after the image build
docker exec cdash-website-1 bash -c "cd /cdash && /usr/bin/git checkout ."

docker exec cdash-website-1 bash -c "\
  ctest \
    -VV \
    -j 3 \
    --schedule-random \
    -DSITENAME=\"${site}\" \
    -DPOSTGRES_VERSION=\"${postgres_version}\" \
    -DSTORAGE_TYPE=\"${storage_type}\" \
    -DSUBMIT_TYPE=\"${submit_type}\" \
    -S \"${ctest_driver}\" \
"
