function(add_js_component_test TestName)
  add_test(
    NAME "Component/${TestName}"
    COMMAND "node_modules/.bin/vitest" "run" "tests/Component/${TestName}.spec.js"
    WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
  )
  set_tests_properties("Component/${TestName}" PROPERTIES
    PROCESSORS 2
  )
  set_property(GLOBAL APPEND PROPERTY CDASH_REGISTERED_TEST_FILES "tests/Component/${TestName}.spec.js")
endfunction()

add_js_component_test(code-box)
add_js_component_test(coverage-viewer)
add_js_component_test(create-project-page)
add_js_component_test(data-table)
add_js_component_test(date-time-selector)
add_js_component_test(filter-row)
add_js_component_test(loading-indicator)
add_js_component_test(monitor-page)
