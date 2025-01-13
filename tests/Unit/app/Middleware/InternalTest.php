<?php

namespace Tests\Unit\app\Middleware;

use App\Http\Middleware\Internal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InternalTest extends TestCase
{
    /**
     * @throws BadRequestException
     */
    public function testAllowsRequestWhenValidAuthHeaderProvided(): void
    {
        /** @var string $app_key */
        $app_key = config('app.key', '');

        $request = Request::create('internal_url');
        $request->headers->set('Authorization', 'Bearer ' . $app_key);

        $response = (new Internal())->handle($request, function () {
            return response('internal content');
        });

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('internal content', $response->getContent());
    }

    /**
     * @throws BadRequestException
     */
    public function testProhibitsRequestWhenInvalidAuthHeaderProvided(): void
    {
        $request = Request::create('internal_url');
        $request->headers->set('Authorization', 'Bearer abcdefg');

        $this->expectException(HttpException::class);
        (new Internal())->handle($request, function () {
            return response('internal content');
        });
    }

    /**
     * @throws BadRequestException
     */
    public function testProhibitsRequestWhenNoAuthHeaderProvided(): void
    {
        $request = Request::create('internal_url');

        $this->expectException(HttpException::class);
        (new Internal())->handle($request, function () {
            return response('internal content');
        });
    }
}
