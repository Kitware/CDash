# These tests should go last so the removal of builds doesn't clobber other tests
add_php_test(deletesubproject)
set_tests_properties(deletesubproject PROPERTIES DEPENDS cypress/e2e/build-configure)

add_test(
  NAME removebuilds
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_removebuilds.php
)
set_tests_properties(removebuilds PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  DEPENDS deletesubproject
)

add_laravel_test(/Feature/AutoRemoveBuildsCommand)
set_tests_properties(/Feature/AutoRemoveBuildsCommand PROPERTIES DEPENDS removebuilds)
