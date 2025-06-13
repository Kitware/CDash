function(set_app_url)
  if(DEFINED ENV{APP_URL})
    set(APP_URL "$ENV{APP_URL}" PARENT_SCOPE)
  elseif(EXISTS ${CDash_SOURCE_DIR}/.env)
    file(STRINGS ${CDash_SOURCE_DIR}/.env env_vars)
    foreach(var IN LISTS env_vars)
      if(var MATCHES "^APP_URL=(.*)$")
        set(APP_URL "${CMAKE_MATCH_1}" PARENT_SCOPE)
        break()
      endif()
    endforeach()
  else()
    set(APP_URL "http://localhost:8080" PARENT_SCOPE)
  endif()
endfunction()

add_test(
  NAME php_style_check
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/php-cs-fixer fix --dry-run --allow-risky=yes
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

# Run PHPStan static analysis tool
add_test(
  NAME php_static_analysis
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/phpstan analyse --memory-limit=8G
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)
set_tests_properties(php_static_analysis PROPERTIES
  DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>" # Disable PHPStan on the UBI image since lcobucci/jwt gets removed
  ENVIRONMENT PHPSTAN_CPU=2
  PROCESSORS 2 # We could eventually do something more intelligent to give PHPStan more CPU based on the current load...
)

# Run the JS linter
add_test(
  NAME eslint_ignore_list
  COMMAND
    awk
    "BEGIN{rc=0}{if(system(\"ls \"\$1\" >/dev/null 2>&1\")){print\"cannot ignore missing file: \"\$1;rc=1}}END{exit rc}"
    ${CMAKE_SOURCE_DIR}/.eslintignore
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)
add_test(
  NAME eslint
  COMMAND npm run eslint
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)
set_tests_properties(eslint PROPERTIES
  FAIL_REGULAR_EXPRESSION " \+[0-9]\+:[0-9]\+ \+warning"
)

add_subdirectory(cypress/e2e)
add_subdirectory(cypress/component)
add_subdirectory(Spec)
