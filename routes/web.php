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

use App\Http\Controllers\CoverageFileController;
use App\Http\Controllers\CreateProjectController;
use App\Http\Controllers\GlobalInvitationController;
use App\Http\Controllers\ProjectInvitationController;
use App\Http\Controllers\UpdateProjectLogoController;
use App\Models\DynamicAnalysis;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

$routeList = ['verify' => true];

if (config('auth.user_registration_form_enabled') === false) {
    $routeList['register'] = false;
}
Auth::routes($routeList);

Route::get('/oauth/{service}', 'OAuthController@socialite');
Route::get('/oauth/callback/{service}', 'OAuthController@callback');

Route::post('/saml2/login', 'Auth\LoginController@saml2Login');

Route::get('/auth/{service}/redirect', 'OAuthController@socialite');

Route::get('/auth/{service}/callback', 'OAuthController@callback');

Route::get('/logout', 'Auth\LoginController@logout');

Route::get('/recoverPassword.php', 'UserController@recoverPassword');
Route::post('/recoverPassword.php', 'UserController@recoverPassword');

Route::get('/login.php', fn () => redirect('/login', 301));

Route::get('ping', function (Response $response) {
    try {
        DB::connection()->getPdo();
        $response->setContent('OK');
    } catch (Exception) {
        $response->setStatusCode(503);
    }
    return $response;
});

Route::get('/index.php', 'IndexController@showIndexPage');
Route::get('/', 'IndexController@showIndexPage');

Route::any('/submit.php', 'SubmissionController@submit');

Route::get('/image/{image}', 'ImageController@image')
    ->whereNumber('image');
Route::get('/displayImage.php', function (Request $request) {
    $imgid = $request->query('imgid');
    return redirect("/image/{$imgid}", 301);
});

Route::get('/builds/{id}', 'BuildController@summary')
    ->whereNumber('id');
Route::permanentRedirect('/build/{id}', url('/builds/{id}'));
Route::get('/buildSummary.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}", 301);
});

Route::get('/builds/{id}/configure', 'BuildController@configure')
    ->whereNumber('id');
Route::permanentRedirect('/build/{id}/configure', url('/builds/{id}/configure'));
Route::get('/viewConfigure.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/configure", 301);
});

Route::get('/builds/{build_id}/tests', 'BuildController@tests')
    ->whereNumber('build_id');

Route::get('/builds/{id}/update', 'BuildController@update')
    ->whereNumber('id');
Route::permanentRedirect('/build/{id}/update', url('/builds/{id}/update'));
Route::get('/viewUpdate.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/update", 301);
});

Route::get('/builds/{id}/notes', 'BuildController@notes')
    ->whereNumber('id');
Route::permanentRedirect('/build/{id}/notes', url('/builds/{id}/notes'));
Route::get('/viewNotes.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/notes", 301);
});

Route::get('/builds/{id}/dynamic_analysis', 'DynamicAnalysisController@viewDynamicAnalysis')
    ->whereNumber('id');
Route::permanentRedirect('/build/{id}/dynamic_analysis', url('/builds/{id}/dynamic_analysis'));
Route::get('/viewDynamicAnalysis.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/dynamic_analysis", 301);
});

Route::get('/builds/{build_id}/dynamic_analysis/{file_id}', 'DynamicAnalysisController@viewDynamicAnalysisFile')
    ->whereNumber(['build_id', 'file_id']);
Route::get('/viewDynamicAnalysisFile.php', function (Request $request) {
    $fileid = $request->integer('id');
    $da_model = DynamicAnalysis::find($fileid);
    if ($da_model === null || Gate::denies('view', $da_model->build)) {
        abort(400, 'Unknown Build.');
    }

    return redirect("/builds/{$da_model->build?->id}/dynamic_analysis/{$fileid}", 301);
});

Route::get('/builds/{build_id}/targets', 'BuildController@targets')
    ->whereNumber('build_id');

Route::get('/builds/{build_id}/commands', 'BuildController@commands')
    ->whereNumber('build_id');

Route::get('/builds/{build_id}/files', 'BuildController@files')
    ->whereNumber('build_id');
Route::permanentRedirect('/build/{build_id}/files', url('/builds/{build_id}/files'));
Route::get('/viewFiles.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/files", 301);
});

Route::get('/builds/{build_id}/files/{file_id}', 'BuildController@build_file')
    ->whereNumber('build_id')
    ->whereNumber('file_id');
Route::permanentRedirect('/build/{build_id}/file/{file_id}', url('/builds/{build_id}/files/{file_id}'));

Route::get('/builds/{build_id}/coverage', 'BuildController@coverage')
    ->whereNumber('build_id');
Route::match(['get', 'post'], '/viewCoverage.php', function (Request $request) {
    $buildid = $request->query('buildid');
    return redirect("/builds/{$buildid}/coverage", 301);
});

Route::get('/builds/{build_id}/coverage/{file_id}', CoverageFileController::class)
    ->whereNumber('build_id')
    ->whereNumber('file_id');
Route::get('/viewCoverageFile.php', function (Request $request) {
    $buildid = $request->integer('buildid');
    $fileid = $request->integer('fileid');
    return redirect("/builds/{$buildid}/coverage/{$fileid}", 301);
});

Route::post('/projects/{project_id}/logo', UpdateProjectLogoController::class)
    ->whereNumber('project_id');

Route::get('/projects/{id}/edit', 'EditProjectController@edit')
    ->whereNumber('id');
Route::permanentRedirect('/project/{id}/edit', url('/projects/{id}/edit'));

Route::get('/projects/new', CreateProjectController::class);
Route::permanentRedirect('/project/new', url('/projects/new'));

Route::get('/projects/{id}/testmeasurements', 'ManageMeasurementsController@show')
    ->whereNumber('id');
Route::permanentRedirect('/project/{id}/testmeasurements', url('/projects/{id}/testmeasurements'));

Route::get('/projects/{id}/ctest_configuration', 'CTestConfigurationController@get')
    ->whereNumber('id');
Route::permanentRedirect('/project/{id}/ctest_configuration', url('/projects/{id}/ctest_configuration'));
Route::get('/generateCTestConfig.php', function (Request $request) {
    $projectid = $request->query('projectid');
    if (!is_numeric($projectid)) {
        abort(400, 'Not a valid projectid!');
    }
    return redirect("/projects/{$projectid}/ctest_configuration", 301);
});

Route::get('/projects/{project_id}/sites', 'ProjectController@sites')
    ->whereNumber('project_id');
Route::get('/viewMap.php', function (Request $request) {
    $project = Project::where('name', $request->query('project'))->first();
    if ($project === null || Gate::denies('view', $project)) {
        abort(403, 'Unknown project');
    }
    return redirect("/projects/{$project->id}/sites", 301);
});

Route::get('/tests/{id}', 'TestController@details')
    ->whereNumber('id');
Route::permanentRedirect('/test/{id}', url('/tests/{id}'));
Route::get('/testDetails.php', function (Request $request) {
    $buildid = $request->query('build');
    $testid = $request->query('test');
    $buildtest = Test::where('buildid', $buildid)->where('id', $testid)->first();
    if ($buildtest !== null) {
        return redirect("/tests/{$buildtest->id}", 301);
    }
    abort(404);
});

Route::get('/overview.php', 'ProjectOverviewController@overview');

// TODO: (williamjallen) This should be in the auth section, but needs to be here until we get rid of Protractor..
Route::get('/manageOverview.php', 'ProjectOverviewController@manageOverview');

Route::match(['get', 'post'], '/projects', 'ViewProjectsController@viewProjects');
Route::permanentRedirect('/viewProjects.php', url('/projects'));
Route::permanentRedirect('/projects/all', url('/projects'));

Route::get('/viewTest.php', 'ViewTestController@viewTest');

Route::get('/queryTests.php', 'TestController@queryTests');
Route::get('/testSummary.php', function (Request $request) {
    $project = Project::find($request->integer('project'));
    if ($project === null || Gate::denies('view', $project)) {
        abort(403, 'Unknown project');
    }

    $queryParams = [
        'project' => $project->name,
        'filtercount' => 1,
        'showfilters' => 1,
        'field1' => 'testname',
        'compare1' => 61,
        'value1' => $request->string('name')->toString(),
    ];
    if ($request->has('date')) {
        $queryParams['date'] = $request->string('date')->toString();
    }

    return redirect(url()->query('/queryTests.php', $queryParams), 301);
});

Route::get('/testOverview.php', 'TestController@testOverview');

Route::get('/compareCoverage.php', 'CoverageController@compareCoverage');

Route::any('/ajax/getviewcoverage.php', 'CoverageController@ajaxGetViewCoverage');

Route::match(['get', 'post'], '/buildOverview.php', 'BuildController@buildOverview');

Route::get('/viewBuildError.php', 'BuildController@viewBuildError');

Route::get('/viewBuildGroup.php', 'BuildController@viewBuildGroup');

Route::get('/viewSubProjects.php', 'SubProjectController@viewSubProjects');

Route::get('/manageSubProject.php', 'SubProjectController@manageSubProject');

Route::get('/projects/{project}/subprojects/dependencies', 'SubProjectController@dependenciesGraph');
Route::get('/viewSubProjectDependenciesGraph.php', function (Request $request) {
    $project = $request->string('project');
    return redirect("/projects/{$project}/subprojects/dependencies", 301);
});

Route::match(['get', 'post'], '/sites/{site}', 'SiteController@viewSite')
    ->whereNumber('site');
Route::get('/viewSite.php', function (Request $request) {
    $siteid = $request->query('siteid');
    return redirect("/sites/$siteid", 301);
});

Route::get('/manageBuildGroup.php', 'BuildController@manageBuildGroup');

Route::get('/users', 'UsersController@users');

Route::get('/projects/{project_id}/members', 'ProjectMembersController@members')
    ->whereNumber('project_id');

Route::get('/invitations/{invitationId}', GlobalInvitationController::class)
    ->whereNumber('invitationId')
    ->name('invitations')
    ->middleware('signed');

// The user must be logged in to access routes in this section.
// Requests from users who are not logged in will be redirected to /login.
Route::middleware(['auth'])->group(function (): void {
    Route::get('/user', 'UserController@userPage');
    Route::permanentRedirect('/user.php', url('/user'));

    // TODO: (williamjallen) send the POST route to a different function
    Route::match(['get', 'post'], '/profile', 'UserController@edit');
    Route::permanentRedirect('/editUser.php', url('/profile'));

    // TODO: (williamjallen) send the POST route to a different function
    Route::get('/subscribeProject.php', 'SubscribeProjectController@subscribeProject');
    Route::post('/subscribeProject.php', 'SubscribeProjectController@subscribeProject');

    Route::get('/manageProjectRoles.php', function (Request $request) {
        if (!$request->has('projectid')) {
            abort(404);
        }
        $projectid = $request->integer('projectidid');
        return redirect("/projects/$projectid/members", 301);
    });

    Route::match(['get', 'post'], '/editSite.php', function (Request $request) {
        if ($request->has('siteid')) {
            $siteid = $request->integer('siteid');
            return redirect("/sites/$siteid", 301);
        } elseif ($request->has('projectid')) {
            $projectid = $request->integer('projectid');
            return redirect("/projects/$projectid/sites", 301);
        }
        return redirect('/sites', 301);
    });

    Route::get('/ajax/buildnote.php', 'BuildController@ajaxBuildNote');

    Route::get('/projects/{projectId}/invitations/{invitationId}', ProjectInvitationController::class)
        ->whereNumber('projectId')
        ->whereNumber('invitationId');

    Route::middleware(['admin'])->group(function (): void {
        Route::get('/authtokens/manage', 'AuthTokenController@manage');

        Route::get('/removeBuilds.php', 'AdminController@removeBuilds');
        Route::post('/removeBuilds.php', 'AdminController@removeBuilds');

        // TODO: (williamjallen) Move this out of the admin-only section, and instead query only
        //       the sites a given user is able to see.
        Route::get('/sites', 'SiteController@siteStatistics');
        Route::permanentRedirect('/siteStatistics.php', url('/sites'));

        Route::get('/monitor', 'MonitorController@monitor');
        Route::get('/monitor.php', fn () => redirect('/monitor', 301));
    });
});
