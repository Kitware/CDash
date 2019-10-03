set(binary_testing_dir "${CDash_SOURCE_DIR}/tests")
set(PHPUNIT "${CDash_SOURCE_DIR}/vendor/bin/phpunit")

function(add_vue_test TestName)
    add_test(
        NAME ${TestName}
        COMMAND "node_modules/.bin/mocha-webpack" --webpack-config "node_modules/laravel-mix/setup/webpack.config.js" --require "tests/setup.js" "tests/${TestName}.spec.js"
        WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
        )
endfunction(add_vue_test)

add_vue_test(Spec/page-header)
add_vue_test(Spec/page-header/header-top)
add_vue_test(Spec/page-header/header-bottom)
add_vue_test(Spec/page-header/header-nav)
add_vue_test(Spec/page-header/header-menu)
