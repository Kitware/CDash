set(CTEST_SITE "${SITENAME}")
set(CTEST_BUILD_NAME "${BUILDNAME}")
set(CTEST_CMAKE_GENERATOR "Unix Makefiles")
set(CTEST_SOURCE_DIRECTORY "/home/kitware/cdash")
set(CTEST_BINARY_DIRECTORY "/home/kitware/cdash/_build")
set(CTEST_UPDATE_COMMAND git)
set(CTEST_UPDATE_VERSION_ONLY 1)

set(cfg_options
  "-DCDASH_DIR_NAME="
  "-DCDASH_SELENIUM_HUB=selenium-hub"
)
if (postgres)
  list(APPEND cfg_options
    "-DCDASH_DB_TYPE=pgsql"
    "-DCDASH_DB_LOGIN=postgres"
    "-DCDASH_DB_HOST=postgres"
    "-DCDASH_DB_PASS=cdash4simpletest")
else()
  list(APPEND cfg_options "-DCDASH_DB_LOGIN=root")
endif()

ctest_start(Continuous)
ctest_update()
ctest_submit(PARTS Update)
ctest_configure(OPTIONS "${cfg_options}")
ctest_submit(PARTS Configure)
ctest_test(RETURN_VALUE test_status CAPTURE_CMAKE_ERROR cmake_errors)
if (NOT "${test_status}" EQUAL 0)
  message(SEND_ERROR "some tests did not pass cleanly")
endif()
ctest_submit(PARTS Test Done)
