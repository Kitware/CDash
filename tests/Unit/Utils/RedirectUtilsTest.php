<?php

namespace Tests\Unit\Utils;

use App\Utils\RedirectUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Tests\TestCase;

class RedirectUtilsTest extends TestCase
{
    /**
     * @throws BadRequestException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL, and a request that reached CDash under a different host.
        URL::useOrigin('http://cdash.example.org/cdash');
        $this->app->instance('request', Request::create('http://localhost:8080/projects'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localUrls(): array
    {
        return [
            'APP_URL' => ['http://cdash.example.org/cdash/projects/5'],
            'APP_URL root' => ['http://cdash.example.org/cdash'],
            'APP_URL query' => ['http://cdash.example.org/cdash?project=Test'],
            'request host' => ['http://localhost:8080/cdash/projects?sort=name'],
            'path' => ['/projects/5'],
            'dots in names' => ['/viewTest.php?buildid=1#.'],
            'dots and backslashes in query' => ['/projects?filter=..\\x'],
        ];
    }

    #[DataProvider('localUrls')]
    public function testAcceptsLocalUrl(string $url): void
    {
        self::assertTrue(RedirectUtils::isLocalUrl($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function offsiteUrls(): array
    {
        return [
            'other site' => ['https://example.org/elsewhere'],
            'protocol-relative' => ['//example.org/elsewhere'],
            'backslash' => ['/\\example.org/elsewhere'],
            'tab' => ["/\t/example.org/elsewhere"],
            'longer host' => ['http://cdash.example.org.example.net/cdash/projects'],
            'longer port' => ['http://localhost:80801/projects'],
            'userinfo' => ['http://localhost:8080@example.org/elsewhere'],
            'backslash userinfo' => ['https://example.org\\@cdash.example.org/cdash/projects'],
            'outside APP_URL path' => ['http://cdash.example.org/cdash-other/projects'],
            'request host outside APP_URL path' => ['http://localhost:8080/projects'],
            'dot segments' => ['http://cdash.example.org/cdash/projects/../../other'],
            'encoded dot segments' => ['/%2e%2E/other'],
            'backslash dot segments' => ['/projects\\..\\..\\other'],
            'javascript' => ['javascript:alert(1)'],
            'empty' => [''],
        ];
    }

    #[DataProvider('offsiteUrls')]
    public function testRejectsOffsiteUrl(string $url): void
    {
        self::assertFalse(RedirectUtils::isLocalUrl($url));
    }
}
