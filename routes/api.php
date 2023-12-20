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

Route::get('/v1/viewProjects.php', 'ViewProjectsController@fetchPageContent');

Route::get('/v1/viewUpdate.php', 'BuildController@viewUpdatePageContent');

Route::get('/v1/viewTest.php', 'ViewTestController@fetchPageContent');

Route::get('/v1/viewBuildError.php', 'BuildController@apiViewBuildError');

Route::get('/v1/viewConfigure.php', 'BuildController@apiViewConfigure');

Route::get('/v1/buildSummary.php', 'BuildController@apiBuildSummary');

Route::match(['get', 'post', 'delete'], '/v1/relateBuilds.php', 'BuildController@apiRelateBuilds');

Route::get('/v1/user.php', 'UserController@userPageContent');

Route::get('/v1/userStatistics.php', 'UserStatisticsController@api');

Route::get('/v1/filterdata.php', 'FilterController@getFilterDataArray');

Route::get('/v1/viewSubProjects.php', 'SubProjectController@apiViewSubProjects');

Route::get('/v1/viewDynamicAnalysis.php', 'DynamicAnalysisController@apiViewDynamicAnalysis');
Route::get('/v1/viewDynamicAnalysisFile.php', 'DynamicAnalysisController@apiViewDynamicAnalysisFile');

Route::get('/v1/buildProperties.php', 'BuildPropertiesController@apiBuildProperties');

Route::get('/v1/compareCoverage.php', 'CoverageController@apiCompareCoverage');

Route::get('/v1/getPreviousBuilds.php', 'BuildController@apiGetPreviousBuilds');

Route::get('/v1/testSummary.php', 'TestController@apiTestSummary');

Route::get('/v1/is_build_expected.php', 'BuildController@apiBuildExpected');

Route::get('/v1/buildUpdateGraph.php', 'BuildController@apiBuildUpdateGraph');

Route::get('/v1/overview.php', 'ProjectOverviewController@apiOverview');

Route::get('/v1/viewNotes.php', 'BuildNoteController@apiViewNotes');

Route::get('/v1/timeline.php', 'TimelineController@apiTimeline');

Route::get('/v1/testOverview.php', 'TestController@apiTestOverview');

Route::match(['get', 'post', 'delete'], '/v1/expectedbuild.php', 'ExpectedBuildController@apiResponse');

Route::middleware(['auth'])->group(function () {
    Route::post('/authtokens/create', 'AuthTokenController@createToken');
    Route::delete('/authtokens/delete/{token_hash}', 'AuthTokenController@deleteToken');

    Route::post('/v1/addUserNote.php', 'UserNoteController@apiAddUserNote');

    Route::get('/v1/createProject.php', 'ProjectController@apiCreateProject');

    Route::get('/v1/manageSubProject.php', 'SubProjectController@apiManageSubProject');

    Route::get('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiGet');
    Route::post('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiPost');
    Route::delete('/v1/manageMeasurements.php', 'ManageMeasurementsController@apiDelete');

    Route::match(['get', 'post'], '/v1/manageOverview.php', 'ProjectOverviewController@apiManageOverview');

    Route::middleware(['admin'])->group(function () {
        Route::get('/authtokens/all', 'AuthTokenController@fetchAll');

        Route::get('/monitor', 'MonitorController@get');
    });
});

// this *MUST* be the last route in the file
Route::any('{url}', 'CDash')->where('url', '.*');
