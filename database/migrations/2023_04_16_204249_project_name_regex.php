<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Doing the processing in PHP like this is a terrible hack, but is unfortunately the only
        // reasonable way of doing this given that we have to support two different databases and
        // have to allow only a limited number of characters.

        // Wrap everything in a single transaction so if something goes wrong we can easily revert it
        DB::transaction(function () {
            $projects = DB::select('SELECT id, name FROM project');

            foreach ($projects as $project) {
                $new_name = preg_replace("/[^a-zA-Z0-9\ +.\-_]/", '_', $project->name);
                $new_name = str_replace('_-_', '_', $new_name);
                DB::table('project')
                    ->where('id', $project->id)
                    ->update(['name' => $new_name]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is a one-way street.  No way to revert here!
    }
};
