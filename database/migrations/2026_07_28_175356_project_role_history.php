<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS project_role_history (
                id bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                projectid bigint REFERENCES project(id) ON DELETE CASCADE NOT NULL,
                userid bigint REFERENCES users(id) ON DELETE CASCADE NOT NULL,
                role projectrole,
                timestamp timestamp(3) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        DB::statement("
            CREATE OR REPLACE FUNCTION log_project_role_change() RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    -- Only log if the project and user still exist.
                    -- If they are being deleted, the history rows would be cascaded.
                    IF EXISTS (SELECT 1 FROM project WHERE id = OLD.projectid) AND
                       EXISTS (SELECT 1 FROM users WHERE id = OLD.userid) THEN
                        INSERT INTO project_role_history (projectid, userid, role)
                        VALUES (OLD.projectid, OLD.userid, NULL);
                    END IF;
                    RETURN OLD;
                ELSIF (TG_OP = 'UPDATE') THEN
                    IF (OLD.role IS DISTINCT FROM NEW.role) THEN
                        INSERT INTO project_role_history (projectid, userid, role)
                        VALUES (NEW.projectid, NEW.userid, NEW.role);
                    END IF;
                    RETURN NEW;
                ELSIF (TG_OP = 'INSERT') THEN
                    INSERT INTO project_role_history (projectid, userid, role)
                    VALUES (NEW.projectid, NEW.userid, NEW.role);
                    RETURN NEW;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER project_role_history_trigger
            AFTER INSERT OR UPDATE OR DELETE ON user2project
            FOR EACH ROW EXECUTE FUNCTION log_project_role_change();
        ');
    }

    public function down(): void
    {
    }
};
