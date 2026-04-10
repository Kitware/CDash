<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $fields_to_update = [
            'homeurl',
            'cvsurl',
            'bugtrackerurl',
            'bugtrackernewissueurl',
            'documentationurl',
            'testingdataurl',
        ];

        foreach ($fields_to_update as $field) {
            DB::update("
                UPDATE project
                SET $field = 'https://' || $field
                WHERE
                    $field NOT LIKE 'http://%'
                    AND $field NOT LIKE 'https://%'
                    AND $field IS NOT NULL
                    AND length($field) > 0
            ");
        }
    }

    public function down(): void
    {
    }
};
