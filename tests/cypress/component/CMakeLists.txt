function(add_cypress_component_test TestName)
  add_test(
    NAME cypress/component/${TestName}
    COMMAND ${NPX_EXE} cypress run
      --component
      --project ${CDash_SOURCE_DIR}
      --spec ${CDash_SOURCE_DIR}/tests/cypress/component/${TestName}.cy.js
  )
  # Cypress tries to put stuff in our home directory, which doesn't work for /var/www.
  set_tests_properties(cypress/component/${TestName} PROPERTIES
    ENVIRONMENT "HOME=${CDash_BINARY_DIR};"
    DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
    RESOURCE_LOCK "cypress"
    PROCESSORS 2
    ATTACHED_FILES_ON_FAIL "${CDash_SOURCE_DIR}/storage/logs/cdash.log"
  )
endfunction()

add_cypress_component_test(data-table)
add_cypress_component_test(loading-indicator)
add_cypress_component_test(coverage-viewer)
