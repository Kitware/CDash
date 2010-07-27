set(testing_dir "${CDash_SOURCE_DIR}/tests")

# function to add a new PHP based coverage test to CDash
#
function(add_php_test TestName)
  add_test(
    ${TestName}
    ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_${TestName}.php
  )
  set_tests_properties(
    ${TestName} PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
endfunction(add_php_test)

add_php_test(install)
add_php_test(compressedtest)
add_php_test(createpublicdashboard)
add_php_test(email)
add_php_test(projectwebpage)
add_php_test(autoremovebuilds)
add_php_test(backup)
add_php_test(backwardcompatibilitytools)
add_php_test(banner)
add_php_test(buildconfigure)
add_php_test(builderrordiff)
add_php_test(buildgroupposition)
add_php_test(buildgrouprule)
add_php_test(buildoverview)
add_php_test(buildtestdiff)
add_php_test(buildusernote)
add_php_test(dailyupdatefile)
add_php_test(edituser)
add_php_test(errorlog)
add_php_test(image)
add_php_test(displayimage)
add_php_test(recomputecrc32)
add_php_test(import)
add_php_test(importbackup)
add_php_test(importbuilds)
add_php_test(iphone)
add_php_test(loggingadministration)
add_php_test(login)
add_php_test(managebanner)
add_php_test(managebackup)
add_php_test(managebuildgroup)
add_php_test(manageclient)
add_php_test(manageprojectroles)
add_php_test(manageusers)
add_php_test(processsubmissions)
add_php_test(projectindb)
add_php_test(pubproject)
add_php_test(querytests)
add_php_test(router)
add_php_test(sitestatistics)
add_php_test(svninfo)
add_php_test(testenv)
add_php_test(testoverview)
add_php_test(userstatistics)
add_php_test(viewbuilderror)
add_php_test(viewchanges)
add_php_test(viewconfigure)
add_php_test(viewdynamicanalysis)
add_php_test(viewdynamicanalysisfile)
add_php_test(viewerrorlog)
add_php_test(viewissues)
add_php_test(viewmap)
add_php_test(subproject)
add_php_test(groupsdescription)
add_php_test(managesubproject)
add_php_test(viewsubprojectdependencies)

add_subdirectory(ctest)

if(CDASH_USE_SELENIUM)
  file(MAKE_DIRECTORY ${CDash_BINARY_DIR}/cmake.git)
  add_subdirectory(selenium)
  if(CLIENT_TEST_SCRIPT1 AND CLIENT_TEST_SCRIPT2)
    set(TEST_CLIENT_MANAGEMENT ON)
  endif()
endif()

if(TEST_CLIENT_MANAGEMENT)
  add_selenium_test(setup_repositories)
  add_test(registerclient ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT1})
  set_tests_properties(
    registerclient PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Nothing to do.*"
  )
  add_test(requestwork_1 
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_1 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_pass ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2},pass)
  set_tests_properties(
    client_pass PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  add_test(requestwork_2 
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_2 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_fail ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2},fail)
  set_tests_properties(
    client_fail PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  add_test(requestwork_3 
    ${PHPUNIT_EXE} ${testing_dir}/selenium/test_requestwork.php ${BROWSER}
  )
  set_tests_properties(
    requestwork_3 PROPERTIES
    FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*"
  )
  add_test(client_running ${CMAKE_CTEST_COMMAND} -VV -S ${CLIENT_TEST_SCRIPT2})
  set_tests_properties(
    client_running PROPERTIES
    PASS_REGULAR_EXPRESSION ".*Test passed.*"
  )
  add_selenium_test(removescheduledbuild)
  add_php_test(deletedailyupdate)
endif()

