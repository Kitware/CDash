<?php

namespace Tests\Feature;

use App\Enums\AuthTokenScope;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfTokenForSessions;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Slides\Saml2\Http\Middleware\ResolveTenant;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;
use Tests\Traits\LogsInThroughLoginForm;

class SessionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;
    use LogsInThroughLoginForm;

    private const PASSWORD = '12345';

    // A state-changing API route that requires a login. Without a project, it returns 404.
    private const API_POST_ROUTE = '/api/v1/manageOverview.php';

    /**
     * Logs in through the web login form and sends the resulting encrypted session cookie with
     * later requests, as a browser would.
     *
     * @return array{string, string} the ID and CSRF token of the session
     */
    private function logInThroughWebRoute(User $user): array
    {
        $cookie = $this->loginThroughForm($user, self::PASSWORD)
            ->assertRedirect()
            ->getCookie((string) config('session.cookie'), false);
        $this->assertNotNull($cookie);
        $session = [session()->getId(), (string) session()->token()];
        $this->continueInNewSessionWith($cookie);

        return $session;
    }

    /**
     * Forgets the session and user held in memory, and sends the given cookie with later requests,
     * as a browser would. Later requests can then only find the login through that cookie.
     */
    private function continueInNewSessionWith(Cookie $cookie): void
    {
        $this->startNewSession();
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(), (string) $cookie->getValue());
    }

    /**
     * Feature tests skip CSRF checks by default, so turn them back on.
     */
    private function enforceCsrfChecks(): void
    {
        $this->app->bind(VerifyCsrfTokenForSessions::class, fn (Application $app): VerifyCsrfTokenForSessions => new class($app, app(Encrypter::class)) extends VerifyCsrfTokenForSessions {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    /**
     * Returns the router, with the HTTP kernel's middleware groups and priority list registered.
     */
    private function router(): Router
    {
        app(Kernel::class);
        return app(Router::class);
    }

    /**
     * @return array<string, int> the position of each middleware class in the route's final order
     */
    private static function middlewarePositions(Router $router, Route $route): array
    {
        return array_flip(array_values(array_filter($router->gatherRouteMiddleware($route), 'is_string')));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function statelessRoutes(): array
    {
        // Submissions without a project are rejected before anything is stored.
        return [
            'health check' => ['GET', '/ping', 200],
            'GET submission without a project' => ['GET', '/submit.php', 400],
            'PUT submission without a project' => ['PUT', '/submit.php', 400],
            'POST submission without a project' => ['POST', '/submit.php', 400],
        ];
    }

    #[DataProvider('statelessRoutes')]
    public function testStatelessRoutesDoNotStartSession(string $method, string $uri, int $status): void
    {
        $this->call($method, $uri)
            ->assertStatus($status)
            ->assertCookieMissing((string) config('session.cookie'))
            ->assertCookieMissing('XSRF-TOKEN');
    }

    public function testOnlyStatelessRoutesSkipSession(): void
    {
        $router = $this->router();

        $routesWithoutSession = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (!in_array(StartSession::class, $router->gatherRouteMiddleware($route), true)) {
                $routesWithoutSession[] = $route->uri();
            }
        }
        sort($routesWithoutSession);

        // These are the routes in routes/stateless.php.
        $this->assertSame(['ping', 'submit.php'], $routesWithoutSession);
    }

    public function testSessionsStartAfterCookiesAreDecrypted(): void
    {
        $router = $this->router();

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $position = self::middlewarePositions($router, $route);
            if (!array_key_exists(StartSession::class, $position)) {
                continue;
            }

            // Otherwise the session would be looked up by the still-encrypted cookie value.
            $this->assertArrayHasKey(EncryptCookies::class, $position, $route->uri());
            $this->assertLessThan($position[StartSession::class], $position[EncryptCookies::class], $route->uri());
        }
    }

    public function testWebRoutesStartEncryptedSession(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertCookie((string) config('session.cookie'), session()->getId());
    }

    public function testApiUsesLoginFromWebRoutes(): void
    {
        [$sessionId] = $this->logInThroughWebRoute(User::factory()->adminUser()->create(['password' => self::PASSWORD]));

        // Only administrators can see this route.
        $this->get('/api/monitor')
            ->assertOk()
            ->assertCookie((string) config('session.cookie'), $sessionId);
    }

    public function testGraphQLUsesLoginFromWebRoutes(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [$sessionId] = $this->logInThroughWebRoute($user);

        $this->graphQL('{ me { id } }')
            ->assertExactJson(['data' => ['me' => ['id' => (string) $user->id]]])
            ->assertCookie((string) config('session.cookie'), $sessionId);
    }

    public function testApiRequiresCsrfTokenFromSessionUsers(): void
    {
        $this->enforceCsrfChecks();
        [, $csrfToken] = $this->logInThroughWebRoute(User::factory()->create(['password' => self::PASSWORD]));

        // The rejection sends the current token, so that the client can retry.
        $this->post(self::API_POST_ROUTE)->assertStatus(419)->assertCookie('XSRF-TOKEN', $csrfToken);
        $this->post(self::API_POST_ROUTE, [], ['X-CSRF-TOKEN' => $csrfToken])->assertNotFound();
    }

    public function testGraphQLRequiresCsrfTokenFromSessionUsers(): void
    {
        $this->enforceCsrfChecks();
        $user = User::factory()->create(['password' => self::PASSWORD]);
        [, $csrfToken] = $this->logInThroughWebRoute($user);

        // Like the frontend, retry with the encrypted token from the cookie set by the rejection.
        $xsrfToken = $this->graphQL('{ me { id } }')
            ->assertStatus(419)
            ->assertCookie('XSRF-TOKEN', $csrfToken)
            ->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($xsrfToken);
        $this->graphQL('{ me { id } }', [], [], ['X-XSRF-TOKEN' => (string) $xsrfToken->getValue()])
            ->assertExactJson(['data' => ['me' => ['id' => (string) $user->id]]]);
    }

    public function testRememberedUsersNeedCsrfToken(): void
    {
        $this->enforceCsrfChecks();
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $recaller = $this->loginThroughForm($user, self::PASSWORD, remember: true)
            ->getCookie(Auth::guard()->getRecallerName(), false);
        $this->assertNotNull($recaller);

        // A browser whose session has expired, but which still has the "remember me" cookie.
        $this->continueInNewSessionWith($recaller);

        // The rejected request logs the user back in, and sends the new session and its token.
        $rejected = $this->post(self::API_POST_ROUTE)->assertStatus(419);
        $session = $rejected->getCookie((string) config('session.cookie'), false);
        $xsrfToken = $rejected->getCookie('XSRF-TOKEN', false);
        $this->assertNotNull($session);
        $this->assertNotNull($xsrfToken);

        $this->withUnencryptedCookie($session->getName(), (string) $session->getValue())
            ->post(self::API_POST_ROUTE, [], ['X-XSRF-TOKEN' => (string) $xsrfToken->getValue()])
            ->assertNotFound();
    }

    public function testBearerTokenRequestsDoNotNeedCsrfToken(): void
    {
        $this->enforceCsrfChecks();
        $user = User::factory()->create();
        ['raw_token' => $rawToken] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');

        $this->withToken($rawToken)->post(self::API_POST_ROUTE)->assertNotFound();
    }

    public function testAnonymousRequestsDoNotNeedCsrfToken(): void
    {
        $this->enforceCsrfChecks();

        $this->post(self::API_POST_ROUTE)->assertUnauthorized();
        $this->graphQL('{ me { id } }')->assertExactJson(['data' => ['me' => null]]);
    }

    public function testSamlRoutesStartSessionBeforeResolvingTenant(): void
    {
        $router = $this->router();

        $samlRoutes = array_filter(
            $router->getRoutes()->getRoutes(),
            fn (Route $route): bool => str_starts_with((string) $route->getName(), 'saml.'),
        );
        $this->assertNotEmpty($samlRoutes);

        foreach ($samlRoutes as $route) {
            $position = self::middlewarePositions($router, $route);
            $this->assertArrayHasKey(StartSession::class, $position);
            $this->assertArrayHasKey(ResolveTenant::class, $position);
            $this->assertLessThan($position[ResolveTenant::class], $position[StartSession::class], (string) $route->getName());
        }
    }
}
