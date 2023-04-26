cmake_minimum_required(VERSION 3.12)
project(CDash)
include(CTest)
configure_file(${CDash_SOURCE_DIR}/CTestCustom.cmake.in
  ${CDash_BINARY_DIR}/CTestCustom.cmake)

# set some cache variables that can be used
# to configure the testing install
set(CDASH_SERVER localhost CACHE STRING "CDash testing server")
set(CDASH_DB_LOGIN "root" CACHE STRING "Database user")
set(CDASH_DB_PASS "" CACHE STRING "Password for database user")
set(CDASH_DB_HOST "localhost" CACHE STRING "Hostname of the database server")
set(CDASH_DB_TYPE "mysql" CACHE STRING "Database type")
set(CDASH_SELENIUM_HUB "localhost" CACHE STRING "Location of selenium server")
option(CDASH_USE_SELENIUM "Should CDash use Selenium to test ajax functionality?" OFF)
option(CDASH_USE_PROTRACTOR "Should CDash use Protractor to test angular functionality?" ON)
set(CMake_SOURCE_DIR CACHE FILEPATH "Path to CMake source dir.  Set this to import tests from CMake")

get_filename_component(CDASH_DIR_NAME_DEFAULT ${CDash_SOURCE_DIR} NAME)
set(CDASH_DIR_NAME "${CDASH_DIR_NAME_DEFAULT}" CACHE STRING "URL suffix. Ie 'http://<CDASH_SERVER>/<CDASH_DIR_NAME>'")
message("CDASH_DIR_NAME = ${CDASH_DIR_NAME}")

if(CDASH_DIR_NAME)
  set(CDASH_TESTING_URL "http://${CDASH_SERVER}/${CDASH_DIR_NAME}")
else()
  set(CDASH_TESTING_URL "http://${CDASH_SERVER}")
endif()

message("Using url: ${CDASH_TESTING_URL}")
make_directory("${CDash_BINARY_DIR}/tmp")

if(NOT DEFINED CDASH_SITE)
  site_name(CDASH_SITE)
endif()

if(NOT DEFINED CDASH_BUILD_NAME)
  set(CDASH_BUILD_NAME "inner")
endif()

# turn the CDash into one that can be tested
set(testing_dir "${CDash_SOURCE_DIR}/app/cdash/tests")
set(laravel_testing_dir "${CDash_SOURCE_DIR}/tests")

if(NOT CDASH_DB_TYPE)
  set(CDASH_DB_TYPE "mysql")
endif(NOT CDASH_DB_TYPE)

set(CDASH_COVERAGE_DIR "${CDash_BINARY_DIR}/xdebugCoverage")
make_directory("${CDASH_COVERAGE_DIR}")
configure_file(${testing_dir}/prepend_coverage.php.in
               ${CDash_BINARY_DIR}/prepend_coverage.php)


# Any tests which need to perform file I/O write files here
make_directory("/tmp/CDashTesting")

# For coverage builds on apache web servers, provide a default
# .htaccess file, but only if explicitly asked to:
option(CDASH_CONFIGURE_HTACCESS_FILE "Create .htaccess file for coverage testing?" OFF)
if(CDASH_CONFIGURE_HTACCESS_FILE)
  configure_file(
    ${testing_dir}/htaccess.in
    ${CDash_SOURCE_DIR}/app/cdash/public/.htaccess)
    # Yes, the output of this configure_file is intentionally in the
    # source directory
endif()

find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php)

if(CDASH_USE_SELENIUM)
  find_program(PHPUNIT_EXE NAMES phpunit PATHS c:/xampp/php /xampp/php vendor/bin)
endif(CDASH_USE_SELENIUM)

# add the new tests directory
add_subdirectory(tests)

# add the old tests directory
add_subdirectory(app/cdash/tests)
