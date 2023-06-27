<?php

use CDash\Config;
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

Route::get('/v1/getversion.php', function () {
    return response(Config::getInstance()->get('CDASH_VERSION'));
});
Route::get('/version', function () {
    return response(Config::getInstance()->get('CDASH_VERSION'));
});

Route::get('/v1/viewProjects.php', 'ViewProjectsController@fetchPageContent');

Route::get('/v1/viewUpdate.php', 'AdminController@viewUpdatePageContent');

Route::get('/v1/viewTest.php', 'ViewTestController@fetchPageContent');

Route::get('/v1/user.php', 'UserController@userPageContent');

Route::get('/v1/userStatistics.php', 'UserStatisticsController@api');

Route::get('/v1/viewSubProjects.php', 'SubProjectController@apiViewSubProjects');

Route::get('/v1/viewDynamicAnalysis.php', 'DynamicAnalysisController@apiViewDynamicAnalysis');
Route::get('/v1/viewDynamicAnalysisFile.php', 'DynamicAnalysisController@apiViewDynamicAnalysisFile');

Route::middleware(['auth'])->group(function () {
    Route::post('/authtokens/create', 'AuthTokenController@createToken');
    Route::delete('/authtokens/delete/{token_hash}', 'AuthTokenController@deleteToken');

    Route::get('/v1/createProject.php', 'ProjectController@apiCreateProject');

    Route::middleware(['admin'])->group(function () {
        Route::get('/authtokens/all', 'AuthTokenController@fetchAll');

        Route::get('/monitor', 'MonitorController@get');
    });
});

// this *MUST* be the last route in the file
Route::any('{url}', 'CDash')->where('url', '.*');
