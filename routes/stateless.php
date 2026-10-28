<?php

/*
|--------------------------------------------------------------------------
| Stateless Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider without any middleware
| group, so they don't start a session, encrypt or queue cookies, or check
| CSRF tokens. Use them for endpoints that clients without a session hit
| often, such as health checks and CTest submissions. Route model binding
| isn't applied to these routes either.
|
*/

use App\Http\Controllers\SubmissionController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('ping', function (Response $response) {
    try {
        DB::connection()->getPdo();
        $response->setContent('OK');
    } catch (Exception) {
        $response->setStatusCode(503);
    }
    return $response;
});

Route::any('/submit.php', SubmissionController::class);
