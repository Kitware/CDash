<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/** clearly this should be a very short term solution */
use PHPUnit_Framework_MockObject_Matcher_Invocation;
class_alias(
    \PHPUnit\Framework\MockObject\Matcher\Invocation::class,
    PHPUnit_Framework_MockObject_Matcher_Invocation::class
);

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
