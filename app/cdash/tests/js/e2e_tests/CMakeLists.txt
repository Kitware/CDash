configure_file(
  ${CMAKE_CURRENT_SOURCE_DIR}/conf.js.in
  ${CMAKE_CURRENT_BINARY_DIR}/conf.js
  @ONLY
)

function(add_protractor_test test_name)
  if(NOT CDASH_USE_PROTRACTOR OR NOT PROTRACTOR_EXE)
    return()
  endif()
  add_test(
    NAME ${test_name}
    COMMAND
      ${PROTRACTOR_EXE}
      ${CMAKE_CURRENT_BINARY_DIR}/conf.js
      --specs ${CMAKE_CURRENT_SOURCE_DIR}/${test_name}.js
  )
endfunction()

add_cypress_e2e_test(manage-overview)
add_cypress_e2e_test(sub-project-dependencies)
add_cypress_e2e_test(manage-build-group)
add_cypress_e2e_test(manage-sub-project)
add_cypress_e2e_test(view-build-error)
add_cypress_e2e_test(view-test)
add_protractor_test(sort_index)
add_cypress_e2e_test(expected-build)
add_protractor_test(remove_build)
add_protractor_test(viewSubProjects)
add_cypress_e2e_test(test-summary)
add_cypress_e2e_test(query-tests)
add_cypress_e2e_test(filter-labels)
add_protractor_test(viewTestPagination)
add_protractor_test(done_build)
add_protractor_test(multiSort)
add_protractor_test(subprojectGroupOrder)
add_cypress_e2e_test(calendar)
add_cypress_e2e_test(colorblind)
add_cypress_e2e_test(daterange)
