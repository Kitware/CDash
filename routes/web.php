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

$routeList = ['verify' => true];

if(config('auth.user_registration_form_enabled') === false) {
    $routeList['register'] = false;
}
Auth::routes($routeList);

Route::match(['get', 'post'], '/install', 'AdminController@install');
Route::permanentRedirect('/install.php', '/install');

Route::get('/oauth/{service}', 'OAuthController@authenticate');
Route::get('/oauth/callback/{service}', 'OAuthController@login')
    ->name('oauth.callback');
Route::post('/saml2/login', 'Auth\LoginController@saml2Login');

Route::get('/auth/{service}/redirect', 'OAuthController@socialite');

Route::get('/auth/{service}/callback', 'OAuthController@callback');

Route::get('/logout', 'Auth\LoginController@logout');

Route::get('/recoverPassword.php', 'UserController@recoverPassword');
Route::post('/recoverPassword.php', 'UserController@recoverPassword');

Route::get('/login.php', function () {
    return redirect('/login', 301);
});

Route::get('ping', function (Response $response) {
    try {
        DB::connection()->getPdo();
        $response->setContent('OK');
    } catch (\Exception $exception) {
        $response->setStatusCode(503);
    }
    return $response;
});

Route::get('/index.php', 'IndexController@showIndexPage');
Route::get('/', 'IndexController@showIndexPage');

Route::any('/submit.php', 'SubmissionController@submit');

Route::get('/image/{image}', 'ImageController@image');
Route::get('/displayImage.php', function (Request $request) {
    $imgid = $request->query('imgid');
    return redirect("/image/{$imgid}", 301);
});

Route::get('/build/{id}', 'BuildController@summary');
Route::get('/buildSummary.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}", 301);
});

Route::get('/build/{id}/configure', 'BuildController@configure');
Route::get('/viewConfigure.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/configure", 301);
});


Route::get('/build/{id}/update', 'BuildController@update');
Route::get('/viewUpdate.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/update", 301);
});

Route::get('/build/{id}/notes', 'BuildController@notes');
Route::get('/viewNotes.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/notes", 301);
});

Route::get('/build/{id}/dynamic_analysis', 'DynamicAnalysisController@viewDynamicAnalysis')
    ->whereNumber('id');
Route::get('/viewDynamicAnalysis.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/build/{$buildid}/dynamic_analysis", 301);
});

Route::get('/project/{id}/edit', 'EditProjectController@edit');
Route::get('/project/new', 'EditProjectController@create');

Route::get('/project/{id}/testmeasurements', 'ManageMeasurementsController@show');

Route::get('/project/{id}/ctest_configuration', 'CTestConfigurationController@get')
    ->whereNumber('id');
Route::get('/generateCTestConfig.php', function (Request $request) {
    $projectid = $request->query('projectid');
    if (!is_numeric($projectid)) {
        abort(400, 'Not a valid projectid!');
    }
    return redirect("/project/{$projectid}/ctest_configuration", 301);
});

Route::get('/test/{id}', 'TestController@details');
Route::get('/testDetails.php', function (Request $request) {
    $buildid = $request->query('build');
    $testid = $request->query('test');
    $buildtest = \App\Models\BuildTest::where('buildid', $buildid)->where('testid', $testid)->first();
    if ($buildtest !== null) {
        return redirect("/test/{$buildtest->id}", 301);
    }
    abort(404);
});

Route::get('/overview.php', 'ProjectOverviewController@overview');

// TODO: (williamjallen) This should be in the auth section, but needs to be here until we get rid of Protractor..
Route::get('/manageOverview.php', 'ProjectOverviewController@manageOverview');

Route::get('/ajax/showtestfailuregraph.php', 'TestController@ajaxTestFailureGraph');

Route::match(['get', 'post'], '/projects', 'ViewProjectsController@viewAllProjects');
Route::permanentRedirect('/viewProjects.php', '/projects');

Route::get('/viewTest.php', 'ViewTestController@viewTest');

Route::get('/queryTests.php', 'TestController@queryTests');

Route::get('/testOverview.php', 'TestController@testOverview');

Route::get('/testSummary.php', 'TestController@testSummary');

Route::match(['get', 'post'], '/viewCoverage.php', 'CoverageController@viewCoverage');

Route::get('/compareCoverage.php', 'CoverageController@compareCoverage');

Route::get('/viewCoverageFile.php', 'CoverageController@viewCoverageFile');

Route::any('/ajax/getviewcoverage.php', 'CoverageController@ajaxGetViewCoverage');

Route::any('/ajax/showcoveragegraph.php', 'CoverageController@ajaxShowCoverageGraph');

Route::match(['get', 'post'], '/buildOverview.php', 'BuildController@buildOverview');

Route::get('/viewBuildError.php', 'BuildController@viewBuildError');

Route::get('/viewBuildGroup.php', 'BuildController@viewBuildGroup');

Route::get('/buildProperties.php', 'BuildPropertiesController@buildProperties');

Route::get('/viewSubProjects.php', 'SubProjectController@viewSubProjects');

Route::get('/manageSubProject.php', 'SubProjectController@manageSubProject');

Route::get('/viewSubProjectDependenciesGraph', 'SubProjectController@dependenciesGraph');
Route::permanentRedirect('/viewSubProjectDependenciesGraph.php', '/viewSubProjectDependenciesGraph');
// TODO: (williamjallen) Replace this /ajax route with an equivalent /api route
Route::get('/ajax/getsubprojectdependencies.php', 'SubProjectController@ajaxDependenciesGraph');

Route::match(['get', 'post'], '/sites/{siteid}', 'SiteController@viewSite')->whereNumber('siteid');
Route::get('/viewSite.php', function (Request $request) {
    $siteid = $request->query('siteid');
    return redirect("/sites/$siteid", 301);
});

Route::get('/viewMap.php', 'MapController@viewMap');

Route::get('/viewFiles.php', 'BuildController@viewFiles');

Route::get('/viewDynamicAnalysisFile.php', 'DynamicAnalysisController@viewDynamicAnalysisFile');

// TODO: (williamjallen) This route is probably not necessary anymore, and should be removed.
Route::get('/ajax/dailyupdatescurl.php', 'ProjectController@ajaxDailyUpdatesCurl');

Route::get('/manageBuildGroup.php', 'BuildController@manageBuildGroup');

// The user must be logged in to access routes in this section.
// Requests from users who are not logged in will be redirected to /login.
Route::middleware(['auth'])->group(function () {
    Route::get('/user', 'UserController@userPage');
    Route::permanentRedirect('/user.php', '/user');

    // TODO: (williamjallen) send the POST route to a different function
    Route::match(['get', 'post'], '/profile', 'UserController@edit');
    Route::permanentRedirect('/editUser.php', '/profile');

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/subscribeProject.php', 'SubscribeProjectController@subscribeProject');
    Route::post('/subscribeProject.php', 'SubscribeProjectController@subscribeProject');

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/manageProjectRoles.php', 'ManageProjectRolesController@viewPage');
    Route::post('/manageProjectRoles.php', 'ManageProjectRolesController@viewPage');
    Route::any('/ajax/finduserproject.php', 'ManageProjectRolesController@ajaxFindUserProject');

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/manageBanner.php', 'ManageBannerController@manageBanner');
    Route::post('/manageBanner.php', 'ManageBannerController@manageBanner');

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/manageCoverage.php', 'CoverageController@manageCoverage');
    Route::post('/manageCoverage.php', 'CoverageController@manageCoverage');

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/editSite.php', 'SiteController@editSite');
    Route::post('/editSite.php', 'SiteController@editSite');

    Route::get('/ajax/buildnote.php', 'BuildController@ajaxBuildNote');

    // TODO: Determine if this route should go in the admin section
    Route::get('/userStatistics.php', 'AdminController@userStatistics');

    Route::middleware(['admin'])->group(function () {
        Route::get('/authtokens/manage', 'AuthTokenController@manage');

        Route::get('/upgrade.php', 'AdminController@upgrade');
        Route::post('/upgrade.php', 'AdminController@upgrade');

        Route::get('/removeBuilds.php', 'AdminController@removeBuilds');
        Route::post('/removeBuilds.php', 'AdminController@removeBuilds');

        // TODO: (williamjallen) Move this out of the admin-only section, and instead query only
        //       the sites a given user is able to see.
        Route::get('/sites', 'SiteController@siteStatistics');
        Route::permanentRedirect('/siteStatistics.php', '/sites');

        Route::get('/manageUsers.php', 'ManageUsersController@showPage');
        Route::post('/manageUsers.php', 'ManageUsersController@showPage');
        Route::any('/ajax/findusers.php', 'ManageUsersController@ajaxFindUsers');

        Route::get('/monitor', 'MonitorController@monitor');
        Route::get('/monitor.php', function () {
            return redirect('/monitor', 301);
        });
    });
});

// this *MUST* be the last route in the file
Route::any('{url}', 'CDash')->where('url', '.*');
