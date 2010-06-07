# run the web unit tests
configure_file(
  "${CDash_SOURCE_DIR}/testing/ctest/unittest.cmake.in"
  "${CDash_BINARY_DIR}/testing/ctest/unittest.cmake"
  @ONLY ESCAPE_QUOTES
  )
add_test(UnitTests
  ${CMAKE_COMMAND} -P "${CDash_BINARY_DIR}/testing/ctest/unittest.cmake")

# Run the web unit tests as an "inner" dashboard using command line php so that
# we can see individual test results on the dashboard...
#
find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php)
message("PHP_EXE='${PHP_EXE}'")
if(PHP_EXE)
  add_test(UnitTestsAsInnerDashboard
    ${PHP_EXE} "${CDash_SOURCE_DIR}/testing/alltests.php")
#  add_test(loginCoverage
#    ${PHP_EXE} "${CDash_SOURCE_DIR}/testing/singletest.php"
#               "${CDash_SOURCE_DIR}/testing/loginCoverage.php"
#          )
endif()

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
  add_test(${TestName} 
    ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V)
  set_tests_properties(${TestName} PROPERTIES DEPENDS UnitTests)
endfunction(add_ctest_test)

add_ctest_test(simple2 Simple simple2 simple2.php)
add_ctest_test(simple Simple simple simple.php)
add_ctest_test(login Simple simple loginCoverage.php)
