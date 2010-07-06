# function to add a new Selenium based coverage test to CDash
#
function(add_selenium_test TestName)
  add_test(
    ${TestName}
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
endfunction(add_selenium_test)

add_selenium_test(addbuildgroup)
add_selenium_test(expectedbuildgroup)
add_selenium_test(buildinfogroup)
add_selenium_test(showtestgraphs)
add_selenium_test(buildsummary)
add_selenium_test(showcoveragegraph)
add_selenium_test(showupdategraph)
add_selenium_test(finduserproject)
add_selenium_test(findusers)
add_selenium_test(buildnote)
add_selenium_test(editsite)
add_selenium_test(subproject2)
add_selenium_test(querytestsfilters)
add_selenium_test(emailsubscription)
