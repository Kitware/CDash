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
  set_property(TEST cypress/e2e/${TestName} APPEND PROPERTY ENVIRONMENT "HOME=${CDash_BINARY_DIR};")
  set_property(TEST cypress/e2e/${TestName} APPEND PROPERTY DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>")
  set_property(TEST cypress/e2e/${TestName} APPEND PROPERTY RESOURCE_LOCK "cypress;APP_URL") # Cypress can only run one at a time due to xvfb issues and needs a consistent APP_URL
  set_property(TEST cypress/e2e/${TestName} APPEND PROPERTY PROCESSORS 2)
endfunction()

# These tests have the correct dependencies set up
add_cypress_e2e_test(user-profile)
set_property(TEST cypress/e2e/user-profile APPEND PROPERTY DEPENDS install_1)

add_cypress_e2e_test(query-tests)
set_property(TEST cypress/e2e/query-tests APPEND PROPERTY DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(view-test)
set_property(TEST cypress/e2e/view-test APPEND PROPERTY DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(test-summary)
set_property(TEST cypress/e2e/test-summary APPEND PROPERTY DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(tests)
set_property(TEST cypress/e2e/tests APPEND PROPERTY DEPENDS cypress/e2e/remove-build)

add_cypress_e2e_test(view-sub-projects)
set_property(TEST cypress/e2e/view-sub-projects APPEND PROPERTY DEPENDS subproject)

add_cypress_e2e_test(sub-project-group-order)
set_property(TEST cypress/e2e/sub-project-group-order APPEND PROPERTY DEPENDS crosssubprojectcoverage)
set_property(TEST cypress/e2e/sub-project-group-order APPEND PROPERTY DEPENDS aggregatesubprojectcoverage)

add_cypress_e2e_test(sub-project-dependencies)
set_property(TEST cypress/e2e/sub-project-dependencies APPEND PROPERTY DEPENDS subproject)

add_cypress_e2e_test(filter-labels)
set_property(TEST cypress/e2e/filter-labels APPEND PROPERTY DEPENDS actualtrilinossubmission)

add_cypress_e2e_test(view-test-pagination)
set_property(TEST cypress/e2e/view-test-pagination APPEND PROPERTY DEPENDS actualtrilinossubmission)

add_cypress_e2e_test(view-coverage)
set_property(TEST cypress/e2e/view-coverage APPEND PROPERTY DEPENDS coveragedirectories)

add_cypress_e2e_test(colorblind)
set_property(TEST cypress/e2e/colorblind APPEND PROPERTY DEPENDS projectwebpage)

add_cypress_e2e_test(calendar)
set_property(TEST cypress/e2e/calendar APPEND PROPERTY DEPENDS projectwebpage)

add_cypress_e2e_test(daterange)
set_property(TEST cypress/e2e/daterange APPEND PROPERTY DEPENDS projectwebpage)

# These tests don't have all of the dependencies worked out
add_cypress_e2e_test(manage-overview)
set_property(TEST cypress/e2e/manage-overview APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(manage-build-group)
set_property(TEST cypress/e2e/manage-build-group APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(manage-sub-project)
set_property(TEST cypress/e2e/manage-sub-project APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(view-build-error)
set_property(TEST cypress/e2e/view-build-error APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(sort-index)
set_property(TEST cypress/e2e/sort-index APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(expected-build)
set_property(TEST cypress/e2e/expected-build APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(remove-build)
set_property(TEST cypress/e2e/remove-build APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(done-build)
set_property(TEST cypress/e2e/done-build APPEND PROPERTY DEPENDS simple2_async)

add_cypress_e2e_test(build-configure)
set_property(TEST cypress/e2e/build-configure APPEND PROPERTY DEPENDS simple2_async)
