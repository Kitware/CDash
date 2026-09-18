<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE INDEX ON testoutput (md5(path || '|' || command || '|' || output))");

        DB::statement("
            DO $$
            DECLARE
                idx text;
            BEGIN
                SELECT indexname INTO idx
                FROM pg_indexes
                WHERE tablename = 'testoutput' AND indexdef ILIKE '%USING hash%';

                IF idx IS NOT NULL THEN
                    EXECUTE 'DROP INDEX ' || quote_ident(idx);
                END IF;
            END $$;
        ");

        DB::statement(<<<'SQL'
                CREATE VIEW tests AS
                    SELECT
                        build2test.id,
                        build2test.buildid,
                        build2test.status,
                        build2test.time,
                        build2test.timemean,
                        build2test.timestd,
                        build2test.timestatus,
                        build2test.newstatus,
                        build2test.details,
                        build2test.testname,
                        build2test.timestatuscategory,
                        build2test.starttime,
                        testoutput.path,
                        testoutput.command,
                        testoutput.output
                    FROM
                        build2test
                        JOIN testoutput ON (build2test.outputid = testoutput.id);
            SQL);

        DB::statement("ALTER VIEW tests ALTER COLUMN id SET DEFAULT nextval('build2test_id_seq'::regclass)");
        DB::statement('ALTER VIEW tests ALTER COLUMN buildid SET DEFAULT 0');
        DB::statement('ALTER VIEW tests ALTER COLUMN time SET DEFAULT 0');
        DB::statement('ALTER VIEW tests ALTER COLUMN timemean SET DEFAULT 0');
        DB::statement('ALTER VIEW tests ALTER COLUMN timestd SET DEFAULT 0');
        DB::statement('ALTER VIEW tests ALTER COLUMN timestatus SET DEFAULT 0');
        DB::statement('ALTER VIEW tests ALTER COLUMN newstatus SET DEFAULT 0');
        DB::statement("ALTER VIEW tests ALTER COLUMN details SET DEFAULT ''");
        DB::statement("ALTER VIEW tests ALTER COLUMN path SET DEFAULT ''");

        DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION tests_view_trigger() RETURNS trigger AS $$
                DECLARE
                    v_outputid bigint;
                    v_old_outputid bigint;
                    v_path varchar(255);
                    v_command text;
                    v_output text;
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        v_path := COALESCE(NEW.path, '');
                        v_command := COALESCE(NEW.command, '');
                        v_output := COALESCE(NEW.output, '');

                        SELECT id INTO v_outputid
                        FROM testoutput
                        WHERE md5(path || '|' || command || '|' || output) = md5(v_path || '|' || v_command || '|' || v_output)
                          AND path = v_path
                          AND command = v_command
                          AND output = v_output
                        LIMIT 1;

                        IF v_outputid IS NULL THEN
                            INSERT INTO testoutput (path, command, output)
                            VALUES (v_path, v_command, v_output)
                            RETURNING id INTO v_outputid;
                        END IF;

                        INSERT INTO build2test (
                            id,
                            buildid,
                            outputid,
                            status,
                            time,
                            timemean,
                            timestd,
                            timestatus,
                            newstatus,
                            details,
                            testname,
                            starttime
                        ) VALUES (
                            COALESCE(NEW.id, nextval('build2test_id_seq'::regclass)),
                            COALESCE(NEW.buildid, 0),
                            v_outputid,
                            NEW.status,
                            COALESCE(NEW.time, 0),
                            COALESCE(NEW.timemean, 0),
                            COALESCE(NEW.timestd, 0),
                            COALESCE(NEW.timestatus, 0::smallint),
                            COALESCE(NEW.newstatus, 0::smallint),
                            COALESCE(NEW.details, ''),
                            NEW.testname,
                            NEW.starttime
                        )
                        RETURNING id, buildid, status, time, timemean, timestd, timestatus, newstatus, details, testname, timestatuscategory, starttime
                        INTO NEW.id, NEW.buildid, NEW.status, NEW.time, NEW.timemean, NEW.timestd, NEW.timestatus, NEW.newstatus, NEW.details, NEW.testname, NEW.timestatuscategory, NEW.starttime;

                        NEW.path := v_path;
                        NEW.command := v_command;
                        NEW.output := v_output;

                        RETURN NEW;

                    ELSIF TG_OP = 'UPDATE' THEN
                        v_path := COALESCE(NEW.path, '');
                        v_command := COALESCE(NEW.command, '');
                        v_output := COALESCE(NEW.output, '');

                        IF NEW.path IS DISTINCT FROM OLD.path
                           OR NEW.command IS DISTINCT FROM OLD.command
                           OR NEW.output IS DISTINCT FROM OLD.output THEN

                            SELECT id INTO v_outputid
                            FROM testoutput
                            WHERE md5(path || '|' || command || '|' || output) = md5(v_path || '|' || v_command || '|' || v_output)
                              AND path = v_path
                              AND command = v_command
                              AND output = v_output
                            LIMIT 1;

                            IF v_outputid IS NULL THEN
                                INSERT INTO testoutput (path, command, output)
                                VALUES (v_path, v_command, v_output)
                                RETURNING id INTO v_outputid;
                            END IF;

                            SELECT outputid INTO v_old_outputid
                            FROM build2test
                            WHERE id = OLD.id;

                            UPDATE build2test SET
                                buildid = COALESCE(NEW.buildid, 0),
                                outputid = v_outputid,
                                status = NEW.status,
                                time = COALESCE(NEW.time, 0),
                                timemean = COALESCE(NEW.timemean, 0),
                                timestd = COALESCE(NEW.timestd, 0),
                                timestatus = COALESCE(NEW.timestatus, 0::smallint),
                                newstatus = COALESCE(NEW.newstatus, 0::smallint),
                                details = COALESCE(NEW.details, ''),
                                testname = NEW.testname,
                                starttime = NEW.starttime
                            WHERE id = OLD.id
                            RETURNING timestatuscategory INTO NEW.timestatuscategory;

                            IF v_old_outputid IS NOT NULL AND v_old_outputid <> v_outputid THEN
                                DELETE FROM testoutput
                                WHERE id = v_old_outputid
                                  AND NOT EXISTS (SELECT 1 FROM build2test WHERE outputid = v_old_outputid);
                            END IF;

                        ELSE
                            UPDATE build2test SET
                                buildid = COALESCE(NEW.buildid, 0),
                                status = NEW.status,
                                time = COALESCE(NEW.time, 0),
                                timemean = COALESCE(NEW.timemean, 0),
                                timestd = COALESCE(NEW.timestd, 0),
                                timestatus = COALESCE(NEW.timestatus, 0::smallint),
                                newstatus = COALESCE(NEW.newstatus, 0::smallint),
                                details = COALESCE(NEW.details, ''),
                                testname = NEW.testname,
                                starttime = NEW.starttime
                            WHERE id = OLD.id
                            RETURNING timestatuscategory INTO NEW.timestatuscategory;
                        END IF;

                        NEW.path := v_path;
                        NEW.command := v_command;
                        NEW.output := v_output;

                        RETURN NEW;

                    ELSIF TG_OP = 'DELETE' THEN
                        DELETE FROM build2test WHERE id = OLD.id;
                        RETURN OLD;
                    END IF;

                    RETURN NULL;
                END;
                $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared(<<<'SQL'
                CREATE TRIGGER tests_trigger
                INSTEAD OF INSERT OR UPDATE OR DELETE ON tests
                FOR EACH ROW
                EXECUTE FUNCTION tests_view_trigger();
            SQL);
    }

    public function down(): void
    {
    }
};
