<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE project_metrics (
                id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                project_id bigint NOT NULL REFERENCES project(id) ON DELETE CASCADE,
                name varchar(25) NOT NULL CHECK (name <> \'\'),
                position integer NOT NULL,
                UNIQUE (project_id, name)
            )
        ');

        DB::statement('
            CREATE TABLE project_metric_values (
                id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                metric_id bigint NOT NULL REFERENCES project_metrics(id) ON DELETE CASCADE,
                user_id bigint REFERENCES users(id) ON DELETE SET NULL,
                value varchar(25) NOT NULL CHECK (value <> \'\'),
                created_at timestamp(3) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
        DB::statement('CREATE INDEX ON project_metric_values (metric_id, created_at DESC)');
        DB::statement('CREATE INDEX ON project_metric_values (user_id)');
    }

    public function down(): void
    {
    }
};
