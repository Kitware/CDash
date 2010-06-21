cmake_minimum_required(VERSION 2.8)
project(CDash NONE)
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

get_filename_component(CDASH_DIR_NAME ${CDash_SOURCE_DIR} NAME)
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
file(WRITE "${CDash_SOURCE_DIR}/testing/config.test.local.php"
"<?php
$configure = array(
  'urlwebsite'       => '${CDASH_TESTING_URL}',
  'outputdirectory'  => '${CDash_BINARY_DIR}/tmp',
  'type'             => 'Nightly',
  'site'             => '${CDASH_SITE}',
  'buildname'        => '${CDASH_BUILD_NAME}',
  'cdash'            => 'http://www.cdash.org/CDash',
  'svnroot'          => '${CDash_SOURCE_DIR}'
  );
?>
")

if(NOT CDASH_DB_TYPE)
  set(CDASH_DB_TYPE "mysql")
endif(NOT CDASH_DB_TYPE)

set(CDASH_COVERAGE_DIR "${CDash_BINARY_DIR}/xdebugCoverage")
make_directory("${CDASH_COVERAGE_DIR}")
configure_file(${CDash_SOURCE_DIR}/append_coverage.php.in 
  ${CDash_BINARY_DIR}/append_coverage.php)

find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php)

file(WRITE "${CDash_SOURCE_DIR}/cdash/config.local.php"
"<?php
$CDASH_DB_NAME = 'cdash4simpletest';
$CDASH_TESTING_MODE = true;
$CDASH_DB_HOST = '${CDASH_DB_HOST}';
$CDASH_DB_LOGIN = '${CDASH_DB_LOGIN}';
$CDASH_DB_PASS = '${CDASH_DB_PASS}';
$CDASH_DB_TYPE = '${CDASH_DB_TYPE}';
$CDASH_COVERAGE_DIR = '${CDASH_COVERAGE_DIR}';
$PHP_EXE = '${PHP_EXE}';
$CDASH_MANAGE_CLIENTS = '1';
?>
")

# add the testing directory
add_subdirectory(testing)
