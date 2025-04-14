function(add_cypress_component_test TestName)
  set_app_url()

  add_test(
    NAME cypress/component/${TestName}
    COMMAND ${NPX_EXE} cypress run
      --component
      --project ${CDash_SOURCE_DIR}
      --spec ${CDash_SOURCE_DIR}/tests/cypress/component/${TestName}.cy.js
      --config baseUrl=${APP_URL}
  )
  # Cypress tries to put stuff in our home directory, which doesn't work for /var/www.
  set_tests_properties(cypress/component/${TestName} PROPERTIES
    ENVIRONMENT "HOME=${CDash_BINARY_DIR};"
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
    RESOURCE_LOCK "cypress"
  )
endfunction()

add_cypress_component_test(data-table)
add_cypress_component_test(loading-indicator)
