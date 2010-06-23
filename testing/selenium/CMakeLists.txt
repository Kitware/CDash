# function to add a new Selenium based coverage test to CDash
#
function(add_selenium_test TestName PreviousTest)
  add_test(
    ${TestName}
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    DEPENDS ${PreviousTest}
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
endfunction(add_selenium_test)

add_selenium_test(addbuildgroup removebuilds)
add_selenium_test(expectedbuildgroup addbuildgroup)
add_selenium_test(buildinfogroup expectedbuildgroup)
add_selenium_test(showtestgraphs buildinfogroup)
add_selenium_test(buildsummary showtestgraphs)
add_selenium_test(showcoveragegraph buildsummary)
add_selenium_test(showupdategraph showcoveragegraph)
