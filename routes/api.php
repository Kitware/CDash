<?php

use App\Http\Controllers\BuildController;
use App\Http\Controllers\BuildGroupController;
use App\Http\Controllers\CDash;
use App\Http\Controllers\DynamicAnalysisController;
use App\Http\Controllers\ExpectedBuildController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\SubProjectController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TestDetailsController;
use App\Http\Controllers\TimelineController;
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

Route::any('/v1/index.php', [IndexController::class, 'apiIndex']);

Route::get('/v1/testDetails.php', [TestDetailsController::class, 'apiTestDetails']);

Route::match(['get', 'post', 'delete'], '/v1/build.php', [BuildController::class, 'restApi']);

Route::get('/v1/filterdata.php', FilterController::class);

Route::get('/v1/viewSubProjects.php', [SubProjectController::class, 'apiViewSubProjects']);

Route::get('/v1/getSubProjectDependencies.php', [SubProjectController::class, 'apiDependenciesGraph']);

Route::get('/v1/viewDynamicAnalysis.php', [DynamicAnalysisController::class, 'apiViewDynamicAnalysis']);

Route::get('/v1/getPreviousBuilds.php', [BuildController::class, 'apiGetPreviousBuilds']);

Route::get('/v1/queryTests.php', [TestController::class, 'apiQueryTests']);

Route::get('/v1/testGraph.php', [TestController::class, 'apiTestGraph']);

Route::get('/v1/is_build_expected.php', [BuildController::class, 'apiBuildExpected']);

Route::get('/v1/buildUpdateGraph.php', [BuildController::class, 'apiBuildUpdateGraph']);

Route::get('/v1/manageBuildGroup.php', [BuildController::class, 'apiManageBuildGroup']);

Route::get('/v1/overview.php', [ProjectOverviewController::class, 'apiOverview']);

Route::get('/v1/timeline.php', TimelineController::class);

Route::get('/v1/testOverview.php', [TestController::class, 'apiTestOverview']);

Route::match(['get', 'post', 'delete'], '/v1/expectedbuild.php', ExpectedBuildController::class);

Route::post('/v1/GitHub/webhook.php', GitHubWebhookController::class);

Route::middleware(['auth'])->group(function (): void {
    Route::get('/v1/manageSubProject.php', [SubProjectController::class, 'apiManageSubProject']);

    Route::match(['get', 'post', 'put', 'delete'], '/v1/subproject.php', [SubProjectController::class, 'apiSubProject']);

    Route::any('/v1/buildgroup.php', BuildGroupController::class);

    Route::match(['get', 'post'], '/v1/manageOverview.php', [ProjectOverviewController::class, 'apiManageOverview']);

    Route::middleware(['admin'])->group(function (): void {
        Route::get('/monitor', [MonitorController::class, 'get']);
    });
});

// this *MUST* be the last route in the file
Route::any('{url}', CDash::class)->where('url', '.*');
