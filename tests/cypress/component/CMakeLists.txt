function(add_cypress_component_test TestName)
  add_test(
    NAME cypress/component/${TestName}
    COMMAND ${NPX_EXE} cypress run
      --component
      --project ${CDash_SOURCE_DIR}
      --spec ${CDash_SOURCE_DIR}/tests/cypress/component/${TestName}.cy.js
  )
  # Cypress tries to put stuff in our home directory, which doesn't work for /var/www.
  set_property(TEST cypress/component/${TestName} APPEND PROPERTY ENVIRONMENT "HOME=${CDash_BINARY_DIR};")
  set_property(TEST cypress/component/${TestName} APPEND PROPERTY DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>")
  set_property(TEST cypress/component/${TestName} APPEND PROPERTY RESOURCE_LOCK "cypress")
  set_property(TEST cypress/component/${TestName} APPEND PROPERTY PROCESSORS 2)
endfunction()

add_cypress_component_test(data-table)
add_cypress_component_test(loading-indicator)
