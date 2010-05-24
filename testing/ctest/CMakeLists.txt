# run the web unit tests
configure_file(
  "${CDash_SOURCE_DIR}/testing/ctest/unittest.cmake.in"
  "${CDash_BINARY_DIR}/testing/ctest/unittest.cmake"
  @ONLY ESCAPE_QUOTES
  )
add_test(UnitTests
  ${CMAKE_COMMAND} -P "${CDash_BINARY_DIR}/testing/ctest/unittest.cmake")

# function to add a new ctest based test to CDash
# 
function(add_ctest_test TestName ProjectName Directory CDashVerifyScript)
  set(DriverScript 
    "${CDash_BINARY_DIR}/testing/ctest/ctestdriver-${TestName}.ctest")
  configure_file(
    "${CDash_SOURCE_DIR}/testing/ctest/ctestdriver.ctest.in"
    "${DriverScript}"
    @ONLY
    )
  add_test(${TestName} 
    ${CMAKE_CTEST_COMMAND} -S "${DriverScript}" -V)
  set_tests_properties(${TestName} PROPERTIES DEPENDS UnitTests)
endfunction(add_ctest_test)


add_ctest_test(simple2 Simple simple2 verify.php)
add_ctest_test(simple Simple simple verify.php)

