# These tests should go last so the removal of builds doesn't clobber other tests
# TODO: Some of these dependencies may not be necessary.
add_php_test(deletesubproject)
set_property(TEST deletesubproject PROPERTY DEPENDS
  cypress/e2e/manage-overview
  cypress/e2e/sub-project-dependencies
  cypress/e2e/manage-build-group
  cypress/e2e/manage-sub-project
  cypress/e2e/view-test
  cypress/e2e/sort-index
  cypress/e2e/expected-build
  cypress/e2e/remove-build
  cypress/e2e/view-sub-projects
  cypress/e2e/query-tests
  cypress/e2e/filter-labels
  cypress/e2e/done-build
  cypress/e2e/sub-project-group-order
  cypress/e2e/calendar
  cypress/e2e/colorblind
  cypress/e2e/daterange
  cypress/e2e/sites
  cypress/e2e/tests
  cypress/e2e/build-configure
)

add_test(
  NAME removebuilds
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_removebuilds.php
)
set_tests_properties(removebuilds PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  DEPENDS deletesubproject
  ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
)
