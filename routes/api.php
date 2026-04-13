<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// NOTE: All routes listed in this file will be prefixed with /api

Route::get('/v1/viewTest.php', 'ViewTestController@fetchPageContent');

Route::get('/v1/testDetails.php', 'TestDetailsController@apiTestDetails');

Route::get('/v1/buildSummary.php', 'BuildController@apiBuildSummary');

Route::match(['get', 'post', 'delete'], '/v1/relateBuilds.php', 'BuildController@apiRelateBuilds');

Route::match(['get', 'post', 'delete'], '/v1/build.php', 'BuildController@restApi');

Route::get('/v1/filterdata.php', 'FilterController@getFilterDataArray');

Route::get('/v1/viewSubProjects.php', 'SubProjectController@apiViewSubProjects');

Route::get('/v1/getSubProjectDependencies.php', 'SubProjectController@apiDependenciesGraph');

Route::get('/v1/viewDynamicAnalysis.php', 'DynamicAnalysisController@apiViewDynamicAnalysis');

Route::get('/v1/getPreviousBuilds.php', 'BuildController@apiGetPreviousBuilds');

Route::get('/v1/queryTests.php', 'TestController@apiQueryTests');

Route::get('/v1/testGraph.php', 'TestController@apiTestGraph');

Route::get('/v1/is_build_expected.php', 'BuildController@apiBuildExpected');

Route::get('/v1/buildUpdateGraph.php', 'BuildController@apiBuildUpdateGraph');

Route::get('/v1/overview.php', 'ProjectOverviewController@apiOverview');

Route::get('/v1/timeline.php', 'TimelineController@apiTimeline');

Route::get('/v1/testOverview.php', 'TestController@apiTestOverview');

Route::match(['get', 'post', 'delete'], '/v1/expectedbuild.php', 'ExpectedBuildController@apiResponse');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/v1/addUserNote.php', 'UserNoteController@apiAddUserNote');

    Route::get('/v1/manageSubProject.php', 'SubProjectController@apiManageSubProject');

    Route::get('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiGet');
    Route::post('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiPost');
    Route::delete('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiDelete');

    Route::match(['get', 'post'], '/v1/manageOverview.php', 'ProjectOverviewController@apiManageOverview');

    Route::middleware(['admin'])->group(function (): void {
        Route::get('/monitor', 'MonitorController@get');
    });
});

// this *MUST* be the last route in the file
Route::any('{url}', 'CDash')->where('url', '.*');
