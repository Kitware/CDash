add_php_test(autoremovebuilds)
add_php_test(autoremovebuilds_on_submit)
add_php_test(deletesubproject)

add_test(
  NAME removebuilds
  COMMAND ${PHP_EXE} ${testing_dir}/singletest.php ${testing_dir}/test_removebuilds.php)
set_tests_properties(
  removebuilds PROPERTIES
  FAIL_REGULAR_EXPRESSION ".*Failures: [1-9]+.*;.*Exceptions: [1-9]+.*")

add_php_test(customclientscript)
