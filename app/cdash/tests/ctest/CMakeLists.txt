# function to add a new ctest based test to CDash
function(add_ctest_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript "${CDash_BINARY_DIR}/app/cdash/tests/ctest/ctestdriver-${TestName}.ctest")
  configure_file(
    "${testing_dir}/ctest/ctestdriver.ctest.in"
    "${DriverScript}"
    @ONLY
  )
  add_test(
    NAME ${TestName}
    COMMAND ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V
  )
endfunction()

# function to add a new ctest based coverage test to CDash
function(add_coverage_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript "${CDash_BINARY_DIR}/app/cdash/tests/ctest/coveragedriver-${TestName}.ctest")
  configure_file(
    "${testing_dir}/ctest/coveragedriver.ctest.in"
    "${DriverScript}"
    @ONLY
  )
  add_test(
    NAME ${TestName}
    COMMAND ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V
  )
endfunction()

if(COVERAGE_COMMAND)
  add_coverage_test(simple InsightExample simple manageCoverageTest.php)
else()
  add_ctest_test(simple InsightExample simple simple.php)
endif()

add_ctest_test(simple2 InsightExample simple2 simple2.php)
add_ctest_test(sameImage InsightExample sameImage sameImage.php)

add_ctest_test(svnUpdates EmailProjectExample simple simple.php)
add_ctest_test(gitUpdates PublicDashboard simple simple.php)

add_test(
  NAME recoverpassword
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_recoverpassword.php
)
set_tests_properties(
  recoverpassword PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
)

add_php_test(submitsortingdata)
add_php_test(indexfilters)
add_php_test(timeline)
add_laravel_test(Feature/Timeline)

add_php_test(nobackup)

# TODO: (sbelsk) revisit why this test fails
#add_php_test(parallelsubmissions)

add_php_test(deferredsubmissions)

if(COVERAGE_COMMAND)
  add_coverage_test(simple_async InsightExample simple manageCoverageTest.php enable_async)
else()
  add_ctest_test(simple_async InsightExample simple simple.php enable_async)
endif()
add_ctest_test(simple2_async InsightExample simple2 simple2.php enable_async)
