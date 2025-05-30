<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This migration takes the place of Laravel's default schema loading functionality to apply CDash-specific
     * logic at schema load time.  The only manual changes to a default pgsql schema dump are as follows:
     *
     * 1. The header information at the top of the dump which sets config values conflicts with Laravel's settings
     *    and was removed manually.
     *
     * 2. All statements regarding the migrations table were removed manually.  This is necessary because
     *    Laravel creates the migrations table for us.
     *
     * 2. All statements regarding the saml2_tenants table were removed manually because the SAML plugin injects
     *    its own migrations which create the table for us.
     *
     */
    public function up(): void
    {
        /**
         * If any migrations have been run, this is an existing database which doesn't need tables to be created.
         */
        if (count(DB::select('SELECT * FROM migrations')) > 0) {
            /**
             * If this is an existing database, we verify that all the pre-4.0 migrations have been run properly.
             * CDash users must upgrade to 4.0 before upgrading to subsequent versions to ensure they have all
             * the 3.x migrations which were squashed in 4.1.
             */
            if (count(DB::select("SELECT * FROM migrations WHERE migration = '2025_05_23_131812_move_diff_to_coverage_summary_table'")) === 0) {
                throw new Exception('Please update to CDash 4.0 before proceeding to update to 4.1+.');
            }

            return;
        }

        $schema_dump = file_get_contents(database_path('pgsql-schema.sql'));
        if ($schema_dump === false) {
            throw new Exception('Unable to read schema file.');
        }

        // Filter out comments...
        $lines = [];
        foreach (explode("\n", $schema_dump) as $line) {
            if (!str_starts_with($line, '--')) {
                $lines[] = $line;
            }
        }

        // Execute each command by splitting on semicolons to determine extent of each command.
        $commands = explode(';', implode("\n", $lines));
        foreach ($commands as $command) {
            if (trim($command) !== '') {
                DB::unprepared($command);
            }
        }
    }

    public function down(): void
    {
    }
};
