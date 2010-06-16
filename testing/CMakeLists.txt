find_program(PHP_EXE NAMES php PATHS c:/xampp/php /xampp/php)
set(testing_dir "${CDash_SOURCE_DIR}/testing")

add_test(install ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_install.php)
set_tests_properties(
  install PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*")

# function to add a new PHP based coverage test to CDash
#
function(add_php_test TestName PreviousTest)
  add_test(
    ${TestName}
    ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    DEPENDS ${PreviousTest}
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
endfunction(add_php_test)

#these four tests are picky about the order that they're run
add_php_test(compressedtest install)
add_php_test(createpublicdashboard compressedtest)
add_php_test(email createpublicdashboard)
add_php_test(projectwebpage email)

add_php_test(backwardcompabilitytools projectwebpage)
add_php_test(banner projectwebpage)
add_php_test(buildconfigure projectwebpage)
add_php_test(builderrordiff projectwebpage)
add_php_test(buildgroupposition projectwebpage)
add_php_test(buildgrouprule projectwebpage)
add_php_test(buildoverview projectwebpage)
add_php_test(buildtestdiff projectwebpage)
add_php_test(buildusernote projectwebpage)
add_php_test(dailyupdatefile projectwebpage)
add_php_test(editsite projectwebpage)
add_php_test(image projectwebpage)
add_php_test(login projectwebpage)
add_php_test(managebuildgroup projectwebpage)
add_php_test(manageprojectroles projectwebpage)
add_php_test(projectindb projectwebpage)
add_php_test(pubproject projectwebpage)
add_php_test(subproject projectwebpage)
add_php_test(testenv projectwebpage)
add_php_test(testoverview projectwebpage)
add_php_test(userstatistics projectwebpage)
add_php_test(viewbuilderror projectwebpage)
add_php_test(viewchanges projectwebpage)
add_php_test(viewdynamicanalysis projectwebpage)
add_php_test(viewdynamicanalysisfile projectwebpage)

add_php_test(managesubproject subproject)
add_php_test(viewsubprojectdependencies subproject)

add_subdirectory(ctest)

#and this one should stay towards the end...
add_php_test(removebuilds viewchanges)
