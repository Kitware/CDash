<?php

namespace Tests\Unit\app;

use Tests\TestCase;
use Tests\Traits\IteratesControllers;

/**
 * CDash has a policy that all abstract controllers should start with "Abstract".  All non-abstract
 * controllers should be marked as final, and should not contain "Abstract" in their name.
 */
class ControllerNameTest extends TestCase
{
    use IteratesControllers;

    public function testAllControllersAbstractOrFinal(): void
    {
        foreach (self::getControllers() as $controller) {
            self::assertTrue(
                $controller->isAbstract() || $controller->isFinal(),
                "Controller error ({$controller->getName()}): All controllers must be either abstract or final."
            );
        }
    }

    public function testAbstractControllers(): void
    {
        foreach (self::getControllers() as $controller) {
            if ($controller->isAbstract()) {
                self::assertStringStartsWith(
                    'Abstract',
                    $controller->getShortName(),
                    "Controller error ({$controller->getName()}): Abstract controller names must begin with 'Abstract'."
                );
            } else {
                self::assertStringStartsNotWith(
                    'Abstract',
                    $controller->getShortName(),
                    "Controller error ({$controller->getName()}): Final controller names must not begin with 'Abstract'."
                );
            }
        }
    }
}
