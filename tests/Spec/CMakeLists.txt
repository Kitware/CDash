function(add_vue_test TestName)
  add_test(
    NAME "Spec/${TestName}"
    COMMAND "node_modules/.bin/jest" "tests/Spec/${TestName}.spec.js"
    WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
  )
endfunction()

add_vue_test(build-configure)
add_vue_test(build-summary)
add_vue_test(edit-project)
add_vue_test(manage-measurements)
add_vue_test(header-menu)
add_vue_test(test-details)
