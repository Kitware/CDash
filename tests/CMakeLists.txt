set(binary_testing_dir "${CDash_SOURCE_DIR}/tests")
set(PHPUNIT "${CDash_SOURCE_DIR}/vendor/bin/phpunit")

function(add_vue_test TestName)
    add_test(
        NAME ${TestName}
        COMMAND "node_modules/.bin/jest" "tests/${TestName}.spec.js"
        WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
        )
endfunction(add_vue_test)

add_vue_test(Spec/build-configure)
add_vue_test(Spec/build-notes)
add_vue_test(Spec/build-summary)
add_vue_test(Spec/edit-project)
add_vue_test(Spec/manage-measurements)
add_vue_test(Spec/page-header/header-menu)
add_vue_test(Spec/test-details)
