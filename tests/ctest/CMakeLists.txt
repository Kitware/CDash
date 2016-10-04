# function to add a new ctest based test to CDash
#
function(add_ctest_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript
    "${CDash_BINARY_DIR}/tests/ctest/ctestdriver-${TestName}.ctest")
  configure_file(
    "${CDash_SOURCE_DIR}/tests/ctest/ctestdriver.ctest.in"
    "${DriverScript}"
    @ONLY
    )
  set(extra_test_count 1)
  foreach(ctest_exe ${CMAKE_CTEST_COMMAND} ${CTEST_EXTRA_EXECUTABLES})
    add_test(${TestName}_${extra_test_count}
      ${ctest_exe} -S "${DriverScript}" -V)
    math(EXPR extra_test_count "${extra_test_count} + 1")
  endforeach(ctest_exe ${CMAKE_CTEST_COMMAND} ${CTEST_EXTRA_EXECUTABLES})
endfunction(add_ctest_test)

# function to add a new ctest based coverage test to CDash
#
function(add_coverage_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript
    "${CDash_BINARY_DIR}/tests/ctest/coveragedriver-${TestName}.ctest")
  configure_file(
    "${CDash_SOURCE_DIR}/tests/ctest/coveragedriver.ctest.in"
    "${DriverScript}"
    @ONLY
    )
  add_test(${TestName}
    ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V)
endfunction(add_coverage_test)

set(CTEST_EXTRA_EXECUTABLES "" CACHE STRING "List of other CTest executables to test")

if(COVERAGE_COMMAND)
  add_coverage_test(simple InsightExample simple manageCoverageTest.php)
else(COVERAGE_COMMAND)
  add_ctest_test(simple InsightExample simple simple.php)
endif(COVERAGE_COMMAND)
add_php_test(api)

add_ctest_test(simple2 InsightExample simple2 simple2.php)
add_ctest_test(login InsightExample simple loginCoverage.php)
add_ctest_test(sameImage InsightExample sameImage sameImage.php)

add_ctest_test(svnUpdates EmailProjectExample simple simple.php)
add_ctest_test(gitUpdates PublicDashboard simple simple.php)

function(add_cdash_test TestName TestDir)
  CONFIGURE_FILE(
    "${CMAKE_CURRENT_SOURCE_DIR}/${TestDir}/test.cmake.in"
    "${CDash_BINARY_DIR}/Tests/${TestDir}/test.cmake"
    @ONLY ESCAPE_QUOTES)
  ADD_TEST(${TestName} ${CMAKE_CTEST_COMMAND}
    -S "${CDash_BINARY_DIR}/Tests/${TestDir}/test.cmake" -V
    --output-log "${CDash_BINARY_DIR}/Tests/${TestDir}/output.log"
    )
endfunction(add_cdash_test)

set(protocol "http")
set(server "${CDASH_SERVER}")
set(path "/${CDASH_DIR_NAME}")

# Give this test plenty of time to run on slower machines:
# Use 1500 or CTEST_TEST_TIMEOUT for long test timeout value,
# whichever is greater.
SET(CMAKE_LONG_TEST_TIMEOUT 1500)
IF(CTEST_TEST_TIMEOUT)
  SET(CMAKE_LONG_TEST_TIMEOUT ${CTEST_TEST_TIMEOUT})
ENDIF(CTEST_TEST_TIMEOUT)
IF(CMAKE_LONG_TEST_TIMEOUT LESS 1500)
  SET(CMAKE_LONG_TEST_TIMEOUT 1500)
ENDIF(CMAKE_LONG_TEST_TIMEOUT LESS 1500)

if(CMake_SOURCE_DIR)
  #just so we don't pollute the CMake binary dir with our testing...
  set(CMake_BINARY_DIR ${CDash_BINARY_DIR})
  set(CMAKE_TEST_GENERATOR ${CMAKE_GENERATOR})

  function(add_cmake_test TestName TestDir)
    CONFIGURE_FILE(
      "${CMake_SOURCE_DIR}/Tests/${TestDir}/test.cmake.in"
      "${CDash_BINARY_DIR}/Tests/${TestDir}/test.cmake"
      @ONLY ESCAPE_QUOTES)
    ADD_TEST(${TestName} ${CMAKE_CTEST_COMMAND}
      -S "${CDash_BINARY_DIR}/Tests/${TestDir}/test.cmake" -V
      --output-log "${CDash_BINARY_DIR}/Tests/${TestDir}/output.log"
      )
  endfunction(add_cmake_test)

  macro(add_failed_submit_test name source build in out log regex)
    # Have variables named source, build and drop_method because the
    # configure_file call expects those variables to be defined.
    #
    set(source "${source}")
    set(build "${build}")
    configure_file("${in}" "${out}" @ONLY)
    add_test(${name} ${CMAKE_CTEST_COMMAND} -S "${out}" -V --output-log "${log}")
    set_tests_properties(${name} PROPERTIES PASS_REGULAR_EXPRESSION "${regex}")
  endmacro()

  set(regex "(Problems when submitting via S*CP")
  set(regex "${regex}|Error message was: ")
  set(regex "${regex}([Cc]ould *n.t resolve host")
  set(regex "${regex}|[Cc]ould *n.t connect to host")
  set(regex "${regex}|The requested URL returned error")
  set(regex "${regex}|libcurl was built with SSL disabled. https: not supported)")
  set(regex "${regex}|Submission method .xmlrpc. not compiled into CTest")
  set(regex "${regex}|Submission successful)")

  set(ctest_coverage_labels_args "")

  foreach(drop_method cp ftp http https scp xmlrpc)
    # Cycle through these values each time through the loop:
    if(ctest_coverage_labels_args STREQUAL "")
      set(ctest_coverage_labels_args "LABELS Everything")
    elseif(ctest_coverage_labels_args STREQUAL "LABELS Everything")
      set(ctest_coverage_labels_args "LABELS 0ArgTest")
    else()
      set(ctest_coverage_labels_args "")
    endif()

    add_failed_submit_test(CTestTestFailedSubmit-${drop_method}
      "${CMake_SOURCE_DIR}/Tests/CTestTest/SmallAndFast"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/${drop_method}"
      "${CMake_SOURCE_DIR}/Tests/CTestTestFailedSubmits/test.cmake.in"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/test-${drop_method}.cmake"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/test-${drop_method}.log"
      "${regex}"
      )
  endforeach()
endif()

add_test(recoverpassword
  ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_recoverpassword.php)
set_tests_properties(
  recoverpassword PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*")

add_php_test(submitsortingdata)
add_php_test(indexfilters)

add_test(enable_async
  ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_enable_async.php
)

add_php_test(nobackup)

if (NOT CDASH_DB_TYPE STREQUAL "pgsql")
  add_php_test(parallelsubmissions)
endif()

if(COVERAGE_COMMAND)
  add_coverage_test(simple_async InsightExample simple manageCoverageTest.php enable_async)
else(COVERAGE_COMMAND)
  add_ctest_test(simple_async InsightExample simple simple.php enable_async)
endif(COVERAGE_COMMAND)
add_ctest_test(simple2_async InsightExample simple2 simple2.php enable_async)

if(CMake_SOURCE_DIR)

  foreach(drop_method cp ftp http https scp xmlrpc)
    # Cycle through these values each time through the loop:
    if(ctest_coverage_labels_args STREQUAL "")
      set(ctest_coverage_labels_args "LABELS Everything")
    elseif(ctest_coverage_labels_args STREQUAL "LABELS Everything")
      set(ctest_coverage_labels_args "LABELS 0ArgTest")
    else()
      set(ctest_coverage_labels_args "")
    endif()

    add_failed_submit_test(CTestTestFailedSubmit-${drop_method}_async
      "${CMake_SOURCE_DIR}/Tests/CTestTest/SmallAndFast"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/${drop_method}"
      "${CMake_SOURCE_DIR}/Tests/CTestTestFailedSubmits/test.cmake.in"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/test-${drop_method}.cmake"
      "${CMake_BINARY_DIR}/Tests/CTestTestFailedSubmits/test-${drop_method}.log"
      "${regex}"
      )
  endforeach()
endif()
