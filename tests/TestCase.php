<?php

namespace Tests;

// This is used by several of the tests, but the Laravel entrypoint is not used for
// such tests, meaning that this could be undefined.
if (!defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
