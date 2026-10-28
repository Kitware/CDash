<?php

namespace App\Http;

use App\Http\Middleware\Admin;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\Internal;
use App\Http\Middleware\JsonOnly;
use App\Http\Middleware\PasswordExpired;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\Timer;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyCsrfTokenForSessions;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Slides\Saml2\Http\Middleware\ResolveTenant;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        CheckForMaintenanceMode::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        TrustProxies::class,
        Timer::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'stateful' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ],
        'web' => [
            'stateful',
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            'password.expired',
        ],
        'api' => [
            'stateful',
            VerifyCsrfTokenForSessions::class,
            ShareErrorsFromSession::class,
            JsonOnly::class,
            // 'throttle:60,1',
            SubstituteBindings::class,
            AuthenticateToken::class,
            AuthenticateSession::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'password.expired' => PasswordExpired::class,
        'admin' => Admin::class,
        'internal' => Internal::class,
    ];

    /**
     * Add CDash's middleware to Laravel's default middleware priority list.
     */
    public function __construct(Application $app, Router $router)
    {
        parent::__construct($app, $router);

        $this->prependToMiddlewarePriority(JsonOnly::class);
        $this->addToMiddlewarePriorityAfter(StartSession::class, ResolveTenant::class);
        $this->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, AuthenticateToken::class);
        $this->addToMiddlewarePriorityBefore(AuthenticateToken::class, VerifyCsrfTokenForSessions::class);
    }
}
