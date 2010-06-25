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
  message("Here's my generator yo: ${CMAKE_GENERATOR}")
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
  
  add_cmake_test(CTestTestBadExe)
  SET_TESTS_PROPERTIES(CTestTestBadExe PROPERTIES
    PASS_REGULAR_EXPRESSION "BAD_COMMAND")
  add_cmake_test(CTestTestCrash)
  SET_TESTS_PROPERTIES(CTestTestCrash PROPERTIES
    PASS_REGULAR_EXPRESSION "SegFault")
  add_cmake_test(CTestTestParallel)
  add_cmake_test(CTestTestResourceLock)
  CONFIGURE_FILE(
    "${CMake_SOURCE_DIR}/Tests/CTestTestRunScript/hello.cmake.in"
    "${CMAKE_CURRENT_BINARY_DIR}/CTestTestRunScript/hello.cmake"
  )
  add_cmake_test(CTestTestRunScript)
  add_cmake_test(CTestTestScheduler)
  SET_TESTS_PROPERTIES(CTestTestScheduler PROPERTIES
    PASS_REGULAR_EXPRESSION "Start 1.*Start 2.*Start 3.*Start 4.*Start 4.*Start 3.*Start 2.*Start 1")
  CONFIGURE_FILE(
    "${CMake_SOURCE_DIR}/Tests/CTestTestStopTime/GetDate.cmake"
    "${CDash_BINARY_DIR}/Tests/CTestTestStopTime/GetDate.cmake"
    COPYONLY)
  add_cmake_test(CTestTestStopTime)
  SET_TESTS_PROPERTIES(CTestTestStopTime PROPERTIES
      PASS_REGULAR_EXPRESSION "The stop time has been passed")
  add_cmake_test(CTestTestSubdir)
  SET_TESTS_PROPERTIES(CTestTestSubdir PROPERTIES
    PASS_REGULAR_EXPRESSION "0 tests failed out of 3")
  #Timeout wants -C Debug.  not sure if it really matters...
  add_cmake_test(CTestTestTimeout)
  SET_TESTS_PROPERTIES(CTestTestTimeout PROPERTIES
    PASS_REGULAR_EXPRESSION "TestTimeout *\\.+ *\\*\\*\\*Timeout.*CheckChild *\\.+ *Passed")

  #special cases:
#  add_failed_submit_test
#      CTestTestFailedSubmits
#
#  CONFIGURE_FILE(
#    "${CMake_SOURCE_DIR}/Tests/CTestTestFailure/testNoBuild.cmake.in"
#    "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testNoBuild.cmake"
#    @ONLY ESCAPE_QUOTES)
#  ADD_TEST(CTestTestNoBuild ${CMAKE_CTEST_COMMAND}
#    -S "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testNoBuild.cmake" -V
#    --output-log "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testOut1.log"
#    )
#  SET_TESTS_PROPERTIES(CTestTestNoBuild PROPERTIES
#    FAIL_REGULAR_EXPRESSION "Error" WILL_FAIL true)
#
#  CONFIGURE_FILE(
#    "${CMake_SOURCE_DIR}/Tests/CTestTestFailure/testNoExe.cmake.in"
#    "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testNoExe.cmake"
#    @ONLY ESCAPE_QUOTES)
#  ADD_TEST(CTestTestNoExe ${CMAKE_CTEST_COMMAND}
#    -S "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testNoExe.cmake" -V
#    --output-log "${CMake_BINARY_DIR}/Tests/CTestTestFailure/testOut2.log"
#    )
#  SET_TESTS_PROPERTIES(CTestTestNoExe PROPERTIES DEPENDS CTestTestNoBuild
#    PASS_REGULAR_EXPRESSION "Could not find executable"
#    FAIL_REGULAR_EXPRESSION "SegFault")
endif()
