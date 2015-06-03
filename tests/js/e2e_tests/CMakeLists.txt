find_program(
  PROTRACTOR_EXE protractor
  PATHS ${CDash_SOURCE_DIR}/node_modules/protractor/bin)

configure_file(
  ${CMAKE_CURRENT_SOURCE_DIR}/conf.js.in
  ${CMAKE_CURRENT_BINARY_DIR}/conf.js
  @ONLY)

function(add_protractor_test test_name)
  if(NOT PROTRACTOR_EXE)
    return()
  endif()
  add_test(
    NAME ${test_name}
    COMMAND ${PROTRACTOR_EXE} ${CMAKE_CURRENT_BINARY_DIR}/conf.js --specs ${CMAKE_CURRENT_SOURCE_DIR}/${test_name}.js
  )
endfunction()

add_protractor_test(viewBuildError)
add_protractor_test(manageSubProject)
add_protractor_test(manageBuildGroup)
