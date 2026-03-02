function(add_jest_test TestName)
  add_test(
    NAME "Spec/${TestName}"
    COMMAND "node_modules/.bin/jest" "tests/Spec/${TestName}.spec.js"
    WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
  )
endfunction()

add_jest_test(build-summary)
add_jest_test(edit-project)
add_jest_test(manage-measurements)
add_jest_test(test-details)
add_jest_test(repository-integrations)
