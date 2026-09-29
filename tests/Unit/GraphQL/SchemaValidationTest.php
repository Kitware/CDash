<?php

namespace Tests\Unit\GraphQL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Lighthouse only performs full schema validation on demand, so some problems (e.g., a non-repeatable
 * directive used more than once on a field) go unnoticed at runtime.  Run the validation explicitly.
 */
class SchemaValidationTest extends TestCase
{
    public function testSchemaIsValid(): void
    {
        // Validation deletes the schema cache file first.  Use a private, disabled cache so we don't
        // delete the shared cache file out from under other tests running in parallel.
        config([
            'lighthouse.schema_cache.enable' => false,
            'lighthouse.schema_cache.path' => sys_get_temp_dir() . '/' . uniqid('lighthouse-schema-', true) . '.php',
        ]);

        self::assertSame(Command::SUCCESS, Artisan::call('lighthouse:validate-schema'));
    }
}
