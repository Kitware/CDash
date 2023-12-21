function(add_vue_test TestName)
    add_test(
      NAME ${TestName}
      COMMAND "node_modules/.bin/jest" "tests/${TestName}.spec.js"
      WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
    )
endfunction()

if(DEFINED ENV{APP_URL})
  set(APP_URL $ENV{APP_URL})
elseif(EXISTS ${CDash_SOURCE_DIR}/.env)
  file(STRINGS ${CDash_SOURCE_DIR}/.env env_vars)
  foreach(var IN LISTS env_vars)
    if(var MATCHES "^APP_URL=(.*)$")
      set(APP_URL "${CMAKE_MATCH_1}")
      break()
    endif()
  endforeach()
else()
  set(APP_URL "http://localhost:8080")
endif()

function(add_cypress_test TestName)
  add_test(
    NAME cypress/${TestName}
    COMMAND ${NPX_EXE} cypress run
      --project ${CDash_SOURCE_DIR}
      --spec ${CDash_SOURCE_DIR}/tests/cypress/e2e/${TestName}.cy.js
      --config baseUrl=${APP_URL}
  )
  set_tests_properties(
      cypress/${TestName}
    PROPERTIES
      ENVIRONMENT "HOME=/cdash;" # Cypress tries to put stuff in our home directory, which doesn't work for /var/www.
  )
endfunction()

add_test(
  NAME php_style_check
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/php-cs-fixer fix --dry-run
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

# Run PHPStan static analysis tool
add_test(
  NAME php_static_analysis
  # Giving PHPStan 2GB of memory makes it run faster, and should be well inside the limits of the CI machines
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/phpstan analyse --memory-limit=2G
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

# Run the JS linter
add_test(
  NAME eslint
  COMMAND npm run eslint
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

add_vue_test(Spec/build-configure)
add_vue_test(Spec/build-notes)
add_vue_test(Spec/build-summary)
add_vue_test(Spec/edit-project)
add_vue_test(Spec/manage-measurements)
add_vue_test(Spec/page-header/header-menu)
add_vue_test(Spec/test-details)

cdash_install()
add_cypress_test(example)
