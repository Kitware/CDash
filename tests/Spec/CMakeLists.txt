function(add_js_unit_test TestName)
  add_test(
    NAME "Spec/${TestName}"
    COMMAND "node_modules/.bin/vitest" "run" "tests/Spec/${TestName}.spec.js"
    WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
  )
endfunction()

add_js_unit_test(flame-chart-layout)
add_js_unit_test(repository-integrations)
add_js_unit_test(utils)
