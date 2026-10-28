<?php

namespace Tests\Unit\GraphQL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Traits\DisablesSchemaCache;

/**
 * Lighthouse only performs full schema validation on demand, so some problems (e.g., a non-repeatable
 * directive used more than once on a field) go unnoticed at runtime.  Run the validation explicitly.
 */
class SchemaValidationTest extends TestCase
{
    use DisablesSchemaCache;

    public function testSchemaIsValid(): void
    {
        $this->disableSchemaCache();

        self::assertSame(Command::SUCCESS, Artisan::call('lighthouse:validate-schema'));
    }
}
