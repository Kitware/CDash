<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\MigrationTest;

class IncreaseSiteInformationCPUColumnsSizeMigration extends MigrationTest
{
    protected function tearDown() : void
    {
        parent::tearDown();
    }

    /**
     * Test case for the migration that increases the size of the CPU columns
     * on the `siteinformation` table.
     *
     * @return void
     */
    public function testIncreaseSiteInformationCPUColumnsSizeMigration()
    {
        Artisan::call('migrate:fresh', [
            '--force' => true]);

        // Rollback the relevant migration.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2021_12_07_110105_increase_site_information_cpu_columns_size.php',
            '--force' => true]);

        // Verify that worked.
        $is_postgres = config('database.default') === 'pgsql';
        if ($is_postgres) {
            $this::assertEquals(-1, Schema::getConnection()->getDoctrineColumn('siteinformation', 'numberlogicalcpus')->getDefault());
            $this::assertEquals(-1, Schema::getConnection()->getDoctrineColumn('siteinformation', 'numberphysicalcpus')->getDefault());
        } else {
            $this::assertEquals('boolean', DB::getSchemaBuilder()->getColumnType('siteinformation', 'numberlogicalcpus'));
            $this::assertEquals('boolean', DB::getSchemaBuilder()->getColumnType('siteinformation', 'numberphysicalcpus'));
        }

        // Run the migrations under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2021_12_07_110105_increase_site_information_cpu_columns_size.php',
            '--force' => true]);

        // Verify that worked.
        if ($is_postgres) {
            $this::assertEquals(0, Schema::getConnection()->getDoctrineColumn('siteinformation', 'numberlogicalcpus')->getDefault());
            $this::assertEquals(0, Schema::getConnection()->getDoctrineColumn('siteinformation', 'numberphysicalcpus')->getDefault());
        } else {
            $this::assertEquals('smallint', DB::getSchemaBuilder()->getColumnType('siteinformation', 'numberlogicalcpus'));
            $this::assertEquals('smallint', DB::getSchemaBuilder()->getColumnType('siteinformation', 'numberphysicalcpus'));
        }

        Artisan::call('migrate:fresh', [
            '--force' => true]);
    }
}
