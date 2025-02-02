add_test(
  NAME php_style_check
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/php-cs-fixer fix --dry-run --allow-risky=yes
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)

# Run PHPStan static analysis tool
add_test(
  NAME php_static_analysis
  # Giving PHPStan 2GB of memory makes it run faster, and should be well inside the limits of the CI machines
  COMMAND ${CMAKE_SOURCE_DIR}/vendor/bin/phpstan analyse --memory-limit=2G
  WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
)
set_tests_properties(php_static_analysis PROPERTIES
  # Disable PHPStan on the UBI image since lcobucci/jwt gets removed
  DISABLED "$<STREQUAL:${CDASH_IMAGE},ubi>"
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

cdash_install()

add_subdirectory(cypress/e2e)
add_subdirectory(cypress/component)
add_subdirectory(Spec)
