# function to add a new ctest based test to CDash
#
function(add_ctest_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript 
    "${CDash_BINARY_DIR}/testing/ctest/ctestdriver-${TestName}.ctest")
  configure_file(
    "${CDash_SOURCE_DIR}/testing/ctest/ctestdriver.ctest.in"
    "${DriverScript}"
    @ONLY
    )
  set(extra_test_count 1)
  foreach(ctest_exe ${CMAKE_CTEST_COMMAND} ${CTEST_EXTRA_EXECUTABLES})
    add_test(${TestName}_${extra_test_count}
      ${ctest_exe} -S "${DriverScript}" -V)
    set_tests_properties(${TestName}_${extra_test_count}
      PROPERTIES DEPENDS projectwebpage)
    math(EXPR extra_test_count "${extra_test_count} + 1")
  endforeach(ctest_exe ${CMAKE_CTEST_COMMAND} ${CTEST_EXTRA_EXECUTABLES})
endfunction(add_ctest_test)

# function to add a new ctest based coverage test to CDash
#
function(add_coverage_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript
    "${CDash_BINARY_DIR}/testing/ctest/coveragedriver-${TestName}.ctest")
  configure_file(
    "${CDash_SOURCE_DIR}/testing/ctest/coveragedriver.ctest.in"
    "${DriverScript}"
    @ONLY
    )
  add_test(${TestName}
    ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V)
  set_tests_properties(${TestName} PROPERTIES DEPENDS projectwebpage)
endfunction(add_coverage_test)

set(CTEST_EXTRA_EXECUTABLES "" CACHE STRING "List of other CTest executables to test")

if(COVERAGE_COMMAND)
  add_coverage_test(simple Simple simple manageCoverageTest.php)
else(COVERAGE_COMMAND)
  add_ctest_test(simple Simple simple simple.php)
endif(COVERAGE_COMMAND)
add_ctest_test(simple2 Simple simple2 simple2.php)
add_ctest_test(login Simple simple loginCoverage.php)
add_ctest_test(sameImage SameImage sameImage sameImage.php)

if(CMake_SOURCE_DIR)
  #just so we don't pollute the CMake binary dir with our testing...
  set(CMake_BINARY_DIR ${CDash_BINARY_DIR})
  set(CMAKE_TEST_GENERATOR ${CMAKE_GENERATOR})

  function(add_cmake_test TestName)
    CONFIGURE_FILE(
      "${CMake_SOURCE_DIR}/Tests/${TestName}/test.cmake.in"
      "${CDash_BINARY_DIR}/Tests/${TestName}/test.cmake"
      @ONLY ESCAPE_QUOTES)
    ADD_TEST(${TestName} ${CMAKE_CTEST_COMMAND}
      -S "${CDash_BINARY_DIR}/Tests/${TestName}/test.cmake" -V
      --output-log "${CDash_BINARY_DIR}/Tests/${TestName}/output.log"
      )
  endfunction(add_cmake_test)

  set(protocol "http")
  set(server "${CDASH_SERVER}")
  set(path "/${CDASH_DIR_NAME}")
  add_cmake_test(CTestSubmitLargeOutput)
  # This test only passes if it fails to submit properly...
  #
  SET_TESTS_PROPERTIES(CTestSubmitLargeOutput PROPERTIES
    PASS_REGULAR_EXPRESSION "Errors occurred during submission")

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
  SET_TESTS_PROPERTIES(CTestSubmitLargeOutput PROPERTIES
    TIMEOUT ${CMAKE_LONG_TEST_TIMEOUT})

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

