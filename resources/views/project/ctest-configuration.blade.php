## This file should be placed in the root directory of your project.
## Then modify the CMakeLists.txt file in the root directory of your
## project to incorporate the testing dashboard.
##
## # The following are required to submit to the CDash dashboard:
##   ENABLE_TESTING()
##   INCLUDE(CTest)

set(CTEST_PROJECT_NAME {{ $project->Name }})
set(CTEST_NIGHTLY_START_TIME {{ $project->NightlyTime }})

set(CTEST_SUBMIT_URL {{ env('APP_URL') }}/submit.php?project={{ urlencode($project->Name) }})

set(CTEST_DROP_SITE_CDASH TRUE)

@if(count($subprojects) > 0)
set(CTEST_PROJECT_SUBPROJECTS
@foreach($subprojects as $subproject)
    {{ $subproject->GetName() }}
@endforeach
)
@endif
