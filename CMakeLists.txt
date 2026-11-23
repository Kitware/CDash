cmake_minimum_required(VERSION 3.22)
project(CDash)
include(CTest)

# set some cache variables that can be used
# to configure the testing install
set(CDASH_SERVER localhost CACHE STRING "CDash testing server")
set(CDASH_IMAGE "$ENV{BASE_IMAGE}" CACHE STRING "Docker image name")

get_filename_component(CDASH_DIR_NAME_DEFAULT ${CDash_SOURCE_DIR} NAME)
set(CDASH_DIR_NAME "${CDASH_DIR_NAME_DEFAULT}" CACHE STRING "URL suffix. Ie 'http://<CDASH_SERVER>/<CDASH_DIR_NAME>'")
message("CDASH_DIR_NAME = ${CDASH_DIR_NAME}")

if(CDASH_DIR_NAME)
  set(CDASH_TESTING_URL "http://${CDASH_SERVER}/${CDASH_DIR_NAME}")
else()
  set(CDASH_TESTING_URL "http://${CDASH_SERVER}")
endif()

message("Using url: ${CDASH_TESTING_URL}")
file(MAKE_DIRECTORY "${CDash_BINARY_DIR}/tmp")

# turn the CDash into one that can be tested
set(testing_dir "${CDash_SOURCE_DIR}/app/cdash/tests")
set(laravel_testing_dir "${CDash_SOURCE_DIR}/tests")

# Any tests which need to perform file I/O write files here
file(MAKE_DIRECTORY "/tmp/CDashTesting")

find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php REQUIRED)

set(PHPUNIT "${CDash_SOURCE_DIR}/vendor/bin/phpunit")

set_property(GLOBAL PROPERTY INSTALL_CDASH_COUNT "0")
function(cdash_install TestName DatabaseName)
  add_test(
    NAME ${TestName}
    COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_install.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )

  if(DatabaseName)
    set_tests_properties(
        ${TestName} PROPERTIES
        ENVIRONMENT "DB_DATABASE=${DatabaseName}"
      )
  endif()
endfunction()

# add the new tests directory
add_subdirectory(tests)

# add the old tests directory
add_subdirectory(app/cdash/tests)

# A test file which isn't registered above never runs, so refuse to configure if any exist.
file(GLOB_RECURSE unregistered_test_files
  RELATIVE "${CDash_SOURCE_DIR}"
  "${laravel_testing_dir}/Feature/*.php"
  "${laravel_testing_dir}/Unit/*.php"
  "${laravel_testing_dir}/Browser/*.php"
  "${laravel_testing_dir}/Spec/*.spec.js"
  "${laravel_testing_dir}/Component/*.spec.js"
)
# Abstract base classes for other tests.
list(REMOVE_ITEM unregistered_test_files
  tests/Browser/Pages/ProjectBuildsPageTestCase.php
  tests/Unit/GraphQL/Directives/DirectiveTestCase.php
)
get_property(registered_test_files GLOBAL PROPERTY CDASH_REGISTERED_TEST_FILES)
list(REMOVE_ITEM unregistered_test_files ${registered_test_files})
if(unregistered_test_files)
  list(JOIN unregistered_test_files "\n  " unregistered_test_files)
  message(FATAL_ERROR "Test files which aren't registered with CTest:\n  ${unregistered_test_files}")
endif()
