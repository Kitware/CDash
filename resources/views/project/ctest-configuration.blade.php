## This file should be placed in the root directory of your project.
## Then modify the CMakeLists.txt file in the root directory of your
## project to incorporate the testing dashboard.
##
## # The following are required to submit to the CDash dashboard:
##   ENABLE_TESTING()
##   INCLUDE(CTest)

set(CTEST_PROJECT_NAME {{ $project->Name }})
set(CTEST_NIGHTLY_START_TIME {{ $project->NightlyTime }})

if(CMAKE_VERSION VERSION_GREATER 3.14)
  set(CTEST_SUBMIT_URL {{ config('app.url') }}/submit.php?project={{ urlencode($project->Name) }})
else()
  set(CTEST_DROP_METHOD "{{ explode("://", config('app.url'))[0] }}")
  set(CTEST_DROP_SITE "{{ explode("://", config('app.url'))[1] }}")
  set(CTEST_DROP_LOCATION "/submit.php?project={{ urlencode($project->Name) }}")
endif()

set(CTEST_DROP_SITE_CDASH TRUE)

@if(count($subprojects) > 0)
set(CTEST_PROJECT_SUBPROJECTS
@foreach($subprojects as $subproject)
    {{ $subproject->name }}
@endforeach()
)
@endif
