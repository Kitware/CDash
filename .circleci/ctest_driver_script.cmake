set(CTEST_SITE "CircleCI 2.0")
set(CTEST_BUILD_NAME "${BUILDNAME}")
set(CTEST_CMAKE_GENERATOR "Unix Makefiles")
set(CTEST_SOURCE_DIRECTORY "/home/kitware/cdash")
set(CTEST_BINARY_DIRECTORY "/home/kitware/cdash/_build")
set(CTEST_UPDATE_COMMAND git)
set(CTEST_UPDATE_VERSION_ONLY 1)

set(cfg_options
  "-DCDASH_TESTING_RENAME_LOGS=true"
  "-DCDASH_DIR_NAME=cdash"
  "-DCDASH_DB_HOST=127.0.0.1"
)
if (postgres)
  list(APPEND cfg_options
    "-DCDASH_DB_TYPE=pgsql"
    "-DCDASH_DB_LOGIN=postgres")
  file(WRITE ${CTEST_BINARY_DIRECTORY}/props.json
          [=[ { "status context": "Postgres" } ]=])
else()
  list(APPEND cfg_options "-DCDASH_DB_LOGIN=root")
  file(WRITE ${CTEST_BINARY_DIRECTORY}/props.json
          [=[ { "status context": "MySQL" } ]=])
endif()

ctest_start(Continuous)
ctest_update()
# Send Update and BuildProperties ASAP so we can set pending status on GitHub.
ctest_submit(PARTS Update)
ctest_submit(CDASH_UPLOAD "${CTEST_BINARY_DIRECTORY}/props.json" CDASH_UPLOAD_TYPE BuildPropertiesJSON)

ctest_configure(OPTIONS "${cfg_options}")
ctest_test(RETURN_VALUE test_status CAPTURE_CMAKE_ERROR cmake_errors)
if (NOT "${test_status}" EQUAL 0)
  message(SEND_ERROR "some tests did not pass cleanly")
endif()
ctest_submit(PARTS Configure Test)
