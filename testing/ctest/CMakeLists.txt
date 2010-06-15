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
  set_tests_properties(${TestName} PROPERTIES DEPENDS projectwebpage)
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

if(COVERAGE_COMMAND)
  add_coverage_test(simple Simple simple manageCoverageTest.php)
else(COVERAGE_COMMAND)
  add_ctest_test(simple Simple simple simple.php)
endif(COVERAGE_COMMAND)
add_ctest_test(simple2 Simple simple2 simple2.php)
add_ctest_test(login Simple simple loginCoverage.php)
add_ctest_test(sameImage SameImage sameImage sameImage.php)
