set(binary_testing_dir "${CDash_SOURCE_DIR}/tests")
set(PHPUNIT "${CDash_SOURCE_DIR}/vendor/bin/phpunit")

function(add_vue_test TestName)
    add_test(
        NAME ${TestName}
        COMMAND "node_modules/.bin/mocha-webpack" --webpack-config "node_modules/laravel-mix/setup/webpack.config.js" --require "tests/setup.js" "tests/Spec/${TestName}.spec.js"
        WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
    )
endfunction(add_vue_test)

function(add_dusk_test TestName)
    add_test(
        NAME ${TestName}
        COMMAND ${PHP_EXE} "artisan" "dusk" "tests/Browser/${TestName}Test.php"
        WORKING_DIRECTORY "${CDash_SOURCE_DIR}"
    )
endfunction(add_dusk_test)

add_dusk_test(BuildPageHeader)

add_vue_test(page-header)
add_vue_test(page-header/header-top)
add_vue_test(page-header/header-bottom)
add_vue_test(page-header/header-nav)
add_vue_test(page-header/header-menu)


