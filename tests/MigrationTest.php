<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Parent class for database migration tests.
 **/
abstract class MigrationTest extends TestCase
{
    protected function tearDown() : void
    {
        // Set all migrations to be part of the same batch.
        DB::table('migrations')->update(['batch' => 1]);
        parent::tearDown();
    }
}
