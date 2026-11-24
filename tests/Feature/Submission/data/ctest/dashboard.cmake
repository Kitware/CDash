cmake_minimum_required(VERSION 3.22)

# Configures, builds, tests and measures the coverage of the sample project, then submits the
# results.  The caller passes CTEST_SITE, CTEST_BINARY_DIRECTORY and CTEST_SUBMIT_URL with -D.

set(CTEST_BUILD_NAME "simple")
set(CTEST_SOURCE_DIRECTORY "${CTEST_SCRIPT_DIRECTORY}/project")
set(CTEST_CMAKE_GENERATOR "Unix Makefiles")
set(CTEST_NOTES_FILES "${CTEST_SCRIPT_DIRECTORY}/${CTEST_SCRIPT_NAME}")
find_program(CTEST_COVERAGE_COMMAND gcov REQUIRED)

set(ENV{CXXFLAGS} "-O0 --coverage")
set(ENV{LDFLAGS} "--coverage")

ctest_start(Experimental)
ctest_configure()
ctest_build()
ctest_test()
ctest_coverage()
ctest_submit(RETURN_VALUE submit_result)
if(NOT submit_result EQUAL 0)
  message(FATAL_ERROR "Failed to submit to ${CTEST_SUBMIT_URL}")
endif()
