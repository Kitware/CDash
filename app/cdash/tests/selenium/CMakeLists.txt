if(NOT BROWSER)
  set(BROWSER firefox PARENT_SCOPE)
  set(BROWSER firefox)
endif()

# function to add a new Selenium based coverage test to CDash
#
function(add_selenium_test TestName)
  add_test(
    NAME ${TestName}
    COMMAND ${PHPUNIT_EXE} ${testing_dir}/selenium/test_${TestName}.php ${BROWSER}
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*;.*Skipped: [1-9]+.*"
  )
endfunction(add_selenium_test)

add_php_test(setup_repositories)

if(COVERAGE_COMMAND)
  add_selenium_test(showcoveragegraph)
endif()

add_selenium_test(finduserproject)
add_selenium_test(findusers)

## Not used with the new GUI
# add_selenium_test(buildnote)

add_selenium_test(editsite)
add_selenium_test(emailsubscription)
