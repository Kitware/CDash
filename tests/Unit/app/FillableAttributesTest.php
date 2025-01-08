<?php

namespace Tests\Unit\app;

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TODO: Figure out how to switch the APP_ENV to "production" in the tests.
 */
class FillableAttributesTest extends TestCase
{
    public function testCreatesModelWhenValidFillableAttributesProvided(): void
    {
        self::expectNotToPerformAssertions();
        new User([
            'firstname' => Str::uuid()->toString(),
            'lastname' => Str::uuid()->toString(),
            'email' => Str::uuid()->toString() . Str::uuid()->toString(),
        ]);
    }

    public function testFailsToCreateModelWhenInvalidFillableAttribute(): void
    {
        self::expectException(MassAssignmentException::class);
        new User([
            'firstname' => Str::uuid()->toString(),
            'lastname' => Str::uuid()->toString(),
            'email' => Str::uuid()->toString() . Str::uuid()->toString(),
            'admin' => true,
        ]);
    }
}
