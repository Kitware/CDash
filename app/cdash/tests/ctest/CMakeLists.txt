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
set_tests_properties(simple PROPERTIES DEPENDS misassignedconfigure)

add_ctest_test(simple2 InsightExample simple2 simple2.php)
set_tests_properties(simple2 PROPERTIES DEPENDS simple)

add_ctest_test(sameImage InsightExample sameImage sameImage.php)
set_tests_properties(sameImage PROPERTIES DEPENDS simple2)

add_ctest_test(svnUpdates EmailProjectExample simple simple.php)
set_tests_properties(svnUpdates PROPERTIES DEPENDS sameImage)

add_ctest_test(gitUpdates PublicDashboard simple simple.php)
set_tests_properties(gitUpdates PROPERTIES DEPENDS svnUpdates)

add_test(
  NAME recoverpassword
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_recoverpassword.php
)
set_tests_properties(recoverpassword PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  DEPENDS gitUpdates
)

add_php_test(submitsortingdata)
set_tests_properties(submitsortingdata PROPERTIES DEPENDS recoverpassword)

add_php_test(indexfilters)
set_tests_properties(indexfilters PROPERTIES DEPENDS submitsortingdata)

add_php_test(timeline)
set_tests_properties(timeline PROPERTIES DEPENDS indexfilters)

add_laravel_test(Feature/Timeline)
set_tests_properties(Feature/Timeline PROPERTIES DEPENDS timeline)

add_php_test(nobackup)
set_tests_properties(nobackup PROPERTIES DEPENDS Feature/Timeline)

# TODO: (sbelsk) revisit why this test fails and add proper dependencies
#add_php_test(parallelsubmissions)

add_php_test(deferredsubmissions)
set_tests_properties(deferredsubmissions PROPERTIES DEPENDS nobackup)

if(COVERAGE_COMMAND)
  add_coverage_test(simple_async InsightExample simple manageCoverageTest.php enable_async)
else()
  add_ctest_test(simple_async InsightExample simple simple.php enable_async)
endif()
set_tests_properties(simple_async PROPERTIES DEPENDS deferredsubmissions)

add_ctest_test(simple2_async InsightExample simple2 simple2.php enable_async)
set_tests_properties(simple2_async PROPERTIES DEPENDS simple_async)
