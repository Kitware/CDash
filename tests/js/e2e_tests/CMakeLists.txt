find_program(
  PROTRACTOR_EXE protractor
  PATHS ${CDash_SOURCE_DIR}/node_modules/protractor/bin)

configure_file(
  ${CMAKE_CURRENT_SOURCE_DIR}/conf.js.in
  ${CMAKE_CURRENT_BINARY_DIR}/conf.js
  @ONLY)

function(add_protractor_test test_name)
  if(NOT PROTRACTOR_EXE OR NOT CDASH_USE_PROTRACTOR)
    return()
  endif()
  add_test(
    NAME ${test_name}
    COMMAND ${PROTRACTOR_EXE} ${CMAKE_CURRENT_BINARY_DIR}/conf.js --specs ${CMAKE_CURRENT_SOURCE_DIR}/${test_name}.js
  )
endfunction()

add_protractor_test(manageBuildGroup)
add_protractor_test(manageOverview)
add_protractor_test(manageSubProject)
add_protractor_test(viewBuildError)
add_protractor_test(viewTest)
add_protractor_test(sort_index)
add_protractor_test(expected_build)
add_protractor_test(remove_build)
add_protractor_test(viewSubProjects)
add_protractor_test(testDetails)
add_protractor_test(testSummary)
add_protractor_test(queryTests)
add_protractor_test(filterLabels)
add_protractor_test(viewTestPagination)
add_protractor_test(done_build)
add_protractor_test(multiSort)
add_protractor_test(subprojectGroupOrder)
add_protractor_test(buildSummary)
add_protractor_test(calendar)
add_protractor_test(manageMeasurements)
