function(add_cypress_e2e_test TestName)
  set_app_url()

  add_test(
    NAME cypress/e2e/${TestName}
    COMMAND ${NPX_EXE} cypress run
      --e2e
      --project ${CDash_SOURCE_DIR}
      --spec ${CDash_SOURCE_DIR}/tests/cypress/e2e/${TestName}.cy.js
      --config baseUrl=${APP_URL}
  )
  # Cypress tries to put stuff in our home directory, which doesn't work for /var/www.
  set_tests_properties(cypress/e2e/${TestName} PROPERTIES
    ENVIRONMENT "HOME=${CDash_BINARY_DIR};"
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
    RESOURCE_LOCK "cypress;APP_URL" # Cypress can only run one at a time due to xvfb issues and needs a consistent APP_URL
  )
endfunction()

# These tests have the correct dependencies set up
add_cypress_e2e_test(user-profile)
set_tests_properties(cypress/e2e/user-profile PROPERTIES DEPENDS install_1)

add_cypress_e2e_test(all-projects)
set_tests_properties(cypress/e2e/all-projects PROPERTIES DEPENDS projectindb)

add_cypress_e2e_test(query-tests)
set_tests_properties(cypress/e2e/query-tests PROPERTIES DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(view-test)
set_tests_properties(cypress/e2e/view-test PROPERTIES DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(test-summary)
set_tests_properties(cypress/e2e/test-summary PROPERTIES DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(tests)
set_tests_properties(cypress/e2e/tests PROPERTIES DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(view-sub-projects)
set_tests_properties(cypress/e2e/view-sub-projects PROPERTIES DEPENDS subproject)

add_cypress_e2e_test(sub-project-group-order)
set_tests_properties(cypress/e2e/sub-project-group-order PROPERTIES DEPENDS crosssubprojectcoverage)
set_tests_properties(cypress/e2e/sub-project-group-order PROPERTIES DEPENDS aggregatesubprojectcoverage)

add_cypress_e2e_test(sub-project-dependencies)
set_tests_properties(cypress/e2e/sub-project-dependencies PROPERTIES DEPENDS subproject)

# These tests don't have all of the dependencies worked out
add_cypress_e2e_test(manage-overview)
set_tests_properties(cypress/e2e/manage-overview PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(manage-build-group)
set_tests_properties(cypress/e2e/manage-build-group PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(manage-sub-project)
set_tests_properties(cypress/e2e/manage-sub-project PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(view-build-error)
set_tests_properties(cypress/e2e/view-build-error PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(sort-index)
set_tests_properties(cypress/e2e/sort-index PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(expected-build)
set_tests_properties(cypress/e2e/expected-build PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(remove-build)
set_tests_properties(cypress/e2e/remove-build PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(filter-labels)
set_tests_properties(cypress/e2e/filter-labels PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(view-test-pagination)
set_tests_properties(cypress/e2e/view-test-pagination PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(done-build)
set_tests_properties(cypress/e2e/done-build PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(calendar)
set_tests_properties(cypress/e2e/calendar PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(colorblind)
set_tests_properties(cypress/e2e/colorblind PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(daterange)
set_tests_properties(cypress/e2e/daterange PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(build-notes)
set_tests_properties(cypress/e2e/build-notes PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(sites)
set_tests_properties(cypress/e2e/sites PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(view-coverage)
set_tests_properties(cypress/e2e/view-coverage PROPERTIES DEPENDS simple2_async)

add_cypress_e2e_test(build-configure)
set_tests_properties(cypress/e2e/build-configure PROPERTIES DEPENDS simple2_async)
