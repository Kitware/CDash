# These tests should go last so the removal of builds doesn't clobber other tests
# TODO: Some of these dependencies may not be necessary.
add_php_test(autoremovebuilds_on_submit)
set_property(TEST autoremovebuilds_on_submit PROPERTY DEPENDS
  cypress/e2e/manage-overview
  cypress/e2e/sub-project-dependencies
  cypress/e2e/manage-build-group
  cypress/e2e/manage-sub-project
  cypress/e2e/view-build-error
  cypress/e2e/view-test
  cypress/e2e/sort-index
  cypress/e2e/expected-build
  cypress/e2e/remove-build
  cypress/e2e/view-sub-projects
  cypress/e2e/test-summary
  cypress/e2e/query-tests
  cypress/e2e/filter-labels
  cypress/e2e/view-test-pagination
  cypress/e2e/done-build
  cypress/e2e/sub-project-group-order
  cypress/e2e/calendar
  cypress/e2e/colorblind
  cypress/e2e/daterange
  cypress/e2e/build-notes
  cypress/e2e/sites
  cypress/e2e/view-coverage
  cypress/e2e/tests
  cypress/e2e/build-configure
)

add_php_test(deletesubproject)
set_tests_properties(deletesubproject PROPERTIES DEPENDS autoremovebuilds_on_submit)

add_test(
  NAME removebuilds
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_removebuilds.php
)
set_tests_properties(removebuilds PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  DEPENDS deletesubproject
)
