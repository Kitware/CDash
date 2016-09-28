if(NOT BROWSER)
  set(BROWSER firefox PARENT_SCOPE)
  set(BROWSER firefox)
endif()

# function to add a new Selenium based coverage test to CDash
#
function(add_selenium_test TestName)
  add_test(
    ${TestName}
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_${TestName}.php ${BROWSER}
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*;.*Skipped: [1-9]+.*"
  )
endfunction(add_selenium_test)

if(CLIENT_TEST_SCRIPT1 AND CLIENT_TEST_SCRIPT2)
  set(TEST_CLIENT_MANAGEMENT ON)
endif()


add_php_test(setup_repositories)

if(TEST_CLIENT_MANAGEMENT)
  add_test(registerclient ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT1})
  set_tests_properties(
    registerclient PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Nothing to do.*"
  )
  add_test(requestwork_1
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_1 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_pass ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2})
  set_tests_properties(
    client_pass PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  add_test(requestwork_2
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_2 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_fail ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2})
  set_tests_properties(
    client_fail PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  add_test(requestwork_3
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_3 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_running ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2})
  set_tests_properties(
    client_running PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  configure_file(
    ${testing_dir}/test_properclientprocessing.php.in
    ${CMAKE_BINARY_DIR}/tests/test_properclientprocessing.php)
  add_configured_php_test(properclientprocessing)
  add_selenium_test(removescheduledbuild)
  add_php_test(deletedailyupdate)
endif()

add_selenium_test(buildinfogroup)

if(COVERAGE_COMMAND)
  add_selenium_test(showcoveragegraph)
endif()

add_selenium_test(showupdategraph)
add_selenium_test(finduserproject)
add_selenium_test(findusers)

## Not used with the new GUI
# add_selenium_test(buildnote)

add_selenium_test(editsite)
add_selenium_test(emailsubscription)
