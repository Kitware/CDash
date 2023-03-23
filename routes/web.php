<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Auth::routes(['verify' => true]);

Route::get('/oauth/{service}', 'OAuthController@authenticate');
Route::get('/oauth/callback/{service}', 'OAuthController@login')
    ->name('oauth.callback');

Route::get('/logout', 'Auth\LoginController@logout');

Route::get('ping', function (Response $response) {
    try {
        DB::connection()->getPdo();
        $response->setContent('OK');
    } catch (\Exception $exception) {
        $response->setStatusCode(503);
    }
    return $response;
});

Route::get('/authtokens/manage', 'AuthTokenController@manage');

Route::get('/image/{image}', 'ImageController@image');

Route::get('/build/{id}', 'BuildController@summary');
Route::get('/buildSummary.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}");
});

Route::get('/build/{id}/configure', 'BuildController@configure');
Route::get('/viewConfigure.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/configure");
});

Route::get('/build/{id}/notes', 'BuildController@notes');
Route::get('/viewNotes.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/notes");
});

Route::get('/project/{id}/edit', 'EditProjectController@edit');
Route::get('/project/new', 'EditProjectController@create');

Route::get('/project/{id}/testmeasurements', 'ManageMeasurementsController@show');

Route::get('/test/{id}', 'TestController@details');
Route::get('/testDetails.php', function (Request $request) {
    $buildid = $request->query('build');
    $testid = $request->query('test');
    $buildtest = \App\Models\BuildTest::where('buildid', $buildid)->where('testid', $testid)->first();
    if ($buildtest) {
        return redirect("/test/{$buildtest->id}");
    }
    abort(404);
});

// API ROUTES /////////////////////////////////////////////////////////////////
// TODO: (williamjallen) The routes in this section should be moved to api.php
//       eventually.  The routing/middleware infrastructure needs to be refactored
//       and this should be moved to api.php as part of that process.  The current
//       blocker is the API middleware which requires bearer tokens instead of
//       standard Laravel web sessions.

Route::get('/api/authtokens/all', 'AuthTokenController@fetchAll');
Route::post('/api/authtokens/create', 'AuthTokenController@createToken');
Route::delete('/api/authtokens/delete/{token_hash}', 'AuthTokenController@deleteToken');

///////////////////////////////////////////////////////////////////////////////

// this *MUST* be the last route in the file
Route::any('{url}', 'CDash')->where('url', '.*');
