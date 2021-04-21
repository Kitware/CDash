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
set(CDASH_DB_PORT "" CACHE STRING "Port to connect to the database")
set(CDASH_DB_HOST "localhost" CACHE STRING "Hostname of the database server")
set(CDASH_DB_TYPE "mysql" CACHE STRING "Database type")
set(CDASH_DB_CONNECTION_TYPE "host" CACHE STRING "Connection type (e.g. host or unix_socket)")
set(CDASH_SELENIUM_HUB "localhost" CACHE STRING "Location of selenium server")
option(CDASH_USE_SELENIUM "Should CDash use Selenium to test ajax functionality?" OFF)
option(CDASH_USE_PROTRACTOR "Should CDash use Protractor to test angular functionality?" ON)
option(CDASH_USE_REDIS_QUEUE "Should CDash use a message queue (Redis) for submitting/processing builds?" OFF)
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
file(WRITE "${testing_dir}/config.test.local.php"
"<?php
$configure = array(
  'urlwebsite'       => '${CDASH_TESTING_URL}',
  'webserver'        => 'http://${CDASH_SERVER}/',
  'webpath'          => '/${CDASH_DIR_NAME}',
  'outputdirectory'  => '${CDash_BINARY_DIR}/tmp',
  'type'             => 'Nightly',
  'site'             => '${CDASH_SITE}',
  'buildname'        => '${CDASH_BUILD_NAME}',
  'cdash'            => 'http://www.cdash.org/CDash',
  'svnroot'          => '${CDash_SOURCE_DIR}',
  );
?>
")

if(NOT CDASH_DB_TYPE)
  set(CDASH_DB_TYPE "mysql")
endif(NOT CDASH_DB_TYPE)

set(CDASH_COVERAGE_DIR "${CDash_BINARY_DIR}/xdebugCoverage")
make_directory("${CDASH_COVERAGE_DIR}")
configure_file(${testing_dir}/prepend_coverage.php.in
               ${CDash_BINARY_DIR}/prepend_coverage.php)

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

# Update composer during configure time.
find_program(COMPOSER
  NAMES composer composer.phar
  PATHS ${CMAKE_CURRENT_SOURCE_DIR}
)
if(NOT COMPOSER)
  file(DOWNLOAD "http://getcomposer.org/composer.phar" "${CMAKE_CURRENT_SOURCE_DIR}/composer.phar")
  set(COMPOSER "${CMAKE_CURRENT_SOURCE_DIR}/composer.phar")
endif()
execute_process(
  COMMAND ${PHP_EXE} ${COMPOSER} install --prefer-dist
  WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
)

# Also run "npm install" during configure time.
find_program(NPM NAMES npm)
execute_process(
  COMMAND ${NPM} install
  WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
)

# Run npm build step.
execute_process(
  COMMAND ${NPM} run dev
  WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
)

# Run PHP style fixer.
execute_process(
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/php-cs-fixer fix
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

if(CDASH_USE_SELENIUM)
  find_program(PHPUNIT_EXE NAMES phpunit PATHS c:/xampp/php /xampp/php vendor/bin)
endif(CDASH_USE_SELENIUM)

option(CDASH_TESTING_RENAME_LOGS
  "If on, kw_web_tester::deleteLog renames cdash.log instead of deleting it"
  OFF)
if(CDASH_TESTING_RENAME_LOGS)
  set(rename_logs "true")
else()
  set(rename_logs "false")
endif()

set(CDASH_USE_LOCAL_DIRECTORY 0)

if (CDASH_USE_REDIS_QUEUE)
  set(CDASH_BERNARD_SUBMISSION "true")
  set(CDASH_BERNARD_DRIVER "new \\Bernard\\Driver\\PredisDriver(new \\Predis\\Client('tcp://localhost'))")
  set(CDASH_BERNARD_CONSUMERS_WHITELIST "array('127.0.0.1')")
else()
  set(CDASH_BERNARD_SUBMISSION "false")
  set(CDASH_BERNARD_DRIVER "false")
  set(CDASH_BERNARD_CONSUMERS_WHITELIST "false")
endif()

file(WRITE "${CDash_SOURCE_DIR}/app/cdash/config/config.local.php"
"<?php
// WARNING: This file is unconditionally overwritten by the CMake configure
// step: [${CMAKE_CURRENT_LIST_FILE}]
//
// Some of the CDash tests also inject config settings below as tests run...
//
date_default_timezone_set('America/New_York');
$CDASH_DB_NAME = 'cdash4simpletest';
$CDASH_PRODUCTION_MODE = false;
$CDASH_TESTING_MODE = true;
$CDASH_REGISTRATION_EMAIL_VERIFY = false;
$CDASH_TESTING_RENAME_LOGS = ${rename_logs};
$CDASH_DB_HOST = '${CDASH_DB_HOST}';
$CDASH_DB_LOGIN = '${CDASH_DB_LOGIN}';
$CDASH_DB_PORT = '${CDASH_DB_PORT}';
$CDASH_DB_PASS = '${CDASH_DB_PASS}';
$CDASH_DB_TYPE = '${CDASH_DB_TYPE}';
$CDASH_DB_CONNECTION_TYPE = '${CDASH_DB_CONNECTION_TYPE}';
$CDASH_COVERAGE_DIR = '${CDASH_COVERAGE_DIR}';
$CDASH_LOG_LEVEL = LOG_DEBUG;
$PHP_EXE = '${PHP_EXE}';
$PHPUNIT_EXE = '${PHPUNIT_EXE}';
$CDASH_MANAGE_CLIENTS = '1';
$CDASH_DEFAULT_GIT_DIRECTORY = '${CDash_BINARY_DIR}';
$CDASH_USE_LOCAL_DIRECTORY = '${CDASH_USE_LOCAL_DIRECTORY}';
$CDASH_BASE_URL = '${CDASH_TESTING_URL}';
$CDASH_BERNARD_SUBMISSION = ${CDASH_BERNARD_SUBMISSION};
$CDASH_BERNARD_DRIVER = ${CDASH_BERNARD_DRIVER};
$CDASH_BERNARD_CONSUMERS_WHITELIST = ${CDASH_BERNARD_CONSUMERS_WHITELIST};
?>
")

execute_process(
  COMMAND ${PHP_EXE} artisan config:migrate
  WORKING_DIRECTORY ${CMAKE_CURRENT_SOURCE_DIR}
)

# add the new tests directory
add_subdirectory(tests)

# add the old tests directory
add_subdirectory(app/cdash/tests)
