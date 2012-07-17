cmake_minimum_required(VERSION 2.8)
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
option(CDASH_USE_SELENIUM "Should CDash use Selenium to test ajax functionality?" OFF)
option(CDASH_PRO "Are we testing CDashPro?" OFF)
set(CMake_SOURCE_DIR CACHE FILEPATH "Path to CMake source dir.  Set this to import tests from CMake")
set(CLIENT_TEST_SCRIPT1 CACHE FILEPATH "Path to client management test script")
set(CLIENT_TEST_SCRIPT2 CACHE FILEPATH "Path to client management test script")

get_filename_component(CDASH_DIR_NAME_DEFAULT ${CDash_SOURCE_DIR} NAME)
set(CDASH_DIR_NAME "${CDASH_DIR_NAME_DEFAULT}" CACHE STRING "URL suffix. Ie 'http://<CDASH_SERVER>/<CDASH_DIR_NAME>'")
message("CDASH_DIR_NAME = ${CDASH_DIR_NAME}")

set(CDASH_TESTING_URL "http://${CDASH_SERVER}/${CDASH_DIR_NAME}")
message("Using url: ${CDASH_TESTING_URL}")
make_directory("${CDash_BINARY_DIR}/tmp")

if(NOT DEFINED CDASH_SITE)
  site_name(CDASH_SITE)
endif()

if(NOT DEFINED CDASH_BUILD_NAME)
  set(CDASH_BUILD_NAME "inner")
endif()

# turn the CDash into one that can be tested
file(WRITE "${CDash_SOURCE_DIR}/tests/config.test.local.php"
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
  'cdashpro'         => '${CDASH_PRO}',
  );
?>
")

if(NOT CDASH_DB_TYPE)
  set(CDASH_DB_TYPE "mysql")
endif(NOT CDASH_DB_TYPE)

set(CDASH_COVERAGE_DIR "${CDash_BINARY_DIR}/xdebugCoverage")
make_directory("${CDASH_COVERAGE_DIR}")
configure_file(${CDash_SOURCE_DIR}/tests/append_coverage.php.in
               ${CDash_BINARY_DIR}/append_coverage.php)

# For coverage builds on apache web servers, provide a default
# .htaccess file, but only if explicitly asked to:
if(CDASH_CONFIGURE_HTACCESS_FILE)
  configure_file(
    ${CDash_SOURCE_DIR}/htaccess.in
    ${CDash_SOURCE_DIR}/.htaccess)
    # Yes, the output of this configure_file is intentionally in the
    # source directory
endif()

find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php)

if(CDASH_USE_SELENIUM)
  find_program(PHPUNIT_EXE NAMES phpunit PATHS c:/xampp/php /xampp/php)
endif(CDASH_USE_SELENIUM)

option(CDASH_TESTING_RENAME_LOGS
  "If on, kw_web_tester::deleteLog renames cdash.log instead of deleting it"
  OFF)
if(CDASH_TESTING_RENAME_LOGS)
  set(rename_logs "true")
else()
  set(rename_logs "false")
endif()

SET(CDASH_USE_LOCAL_DIRECTORY 0)
IF(${CDASH_PRO})
  SET(CDASH_USE_LOCAL_DIRECTORY 1)
ENDIF(${CDASH_PRO})

file(WRITE "${CDash_SOURCE_DIR}/cdash/config.local.php"
"<?php
// WARNING: This file is unconditionally overwritten by the CMake configure
// step: [${CMAKE_CURRENT_LIST_FILE}]
//
// Some of the CDash tests also inject config settings below as tests run...
//
$CDASH_DB_NAME = 'cdash4simpletest';
$CDASH_TESTING_MODE = true;
$CDASH_REGISTRATION_EMAIL_VERIFY = false;
$CDASH_TESTING_RENAME_LOGS = ${rename_logs};
$CDASH_DB_HOST = '${CDASH_DB_HOST}';
$CDASH_DB_LOGIN = '${CDASH_DB_LOGIN}';
$CDASH_DB_PORT = '${CDASH_DB_PORT}';
$CDASH_DB_PASS = '${CDASH_DB_PASS}';
$CDASH_DB_TYPE = '${CDASH_DB_TYPE}';
$CDASH_COVERAGE_DIR = '${CDASH_COVERAGE_DIR}';
$PHP_EXE = '${PHP_EXE}';
$PHPUNIT_EXE = '${PHPUNIT_EXE}';
$CDASH_MANAGE_CLIENTS = '1';
$CDASH_DEFAULT_GIT_DIRECTORY = '${CDash_BINARY_DIR}';
$CDASH_USE_LOCAL_DIRECTORY = '${CDASH_USE_LOCAL_DIRECTORY}';
?>
")

# add the tests directory
add_subdirectory(tests)
