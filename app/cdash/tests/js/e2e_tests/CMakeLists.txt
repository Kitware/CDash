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

add_protractor_test(multiSort)
