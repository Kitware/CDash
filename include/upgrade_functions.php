<?php
// Helper function to alter a table
function AddTableField($table, $field, $mySQLType, $pgSqlType, $default)
{
    include dirname(__DIR__) . '/config/config.php';

    $sql = '';
    if ($default !== false) {
        $sql = " DEFAULT '" . $default . "'";
    }

    $query = pdo_query('SELECT ' . $field . ' FROM ' . $table . ' LIMIT 1');
    if (!$query) {
        add_log("Adding $field to $table", 'AddTableField');
        if ($CDASH_DB_TYPE == 'pgsql') {
            pdo_query('ALTER TABLE "' . $table . '" ADD "' . $field . '" ' . $pgSqlType . $sql);
        } else {
            pdo_query('ALTER TABLE ' . $table . ' ADD ' . $field . ' ' . $mySQLType . $sql);
        }

        add_last_sql_error('AddTableField');
        add_log("Done adding $field to $table", 'AddTableField');
    }
}

/** Remove a table field */
function RemoveTableField($table, $field)
{
    include dirname(__DIR__) . '/config/config.php';
    $query = pdo_query('SELECT ' . $field . ' FROM ' . $table . ' LIMIT 1');
    if ($query) {
        add_log("Droping $field from $table", 'DropTableField');
        if ($CDASH_DB_TYPE == 'pgsql') {
            pdo_query('ALTER TABLE "' . $table . '" DROP COLUMN "' . $field . '"');
        } else {
            pdo_query('ALTER TABLE ' . $table . ' DROP ' . $field);
        }
        add_last_sql_error('DropTableField');
        add_log("Done droping $field from $table", 'DropTableField');
    }
}

// Rename a table vield
function RenameTableField($table, $field, $newfield, $mySQLType, $pgSqlType, $default)
{
    include dirname(__DIR__) . '/config/config.php';
    $query = pdo_query('SELECT ' . $field . ' FROM ' . $table . ' LIMIT 1');
    if ($query) {
        add_log("Changing $field to $newfield for $table", 'RenameTableField');
        if ($CDASH_DB_TYPE == 'pgsql') {
            pdo_query('ALTER TABLE "' . $table . '" RENAME "' . $field . '" TO "' . $newfield . '"');
            pdo_query('ALTER TABLE "' . $table . '" ALTER COLUMN "' . $newfield . '" TYPE ' . $pgSqlType);
            pdo_query('ALTER TABLE "' . $table . '" ALTER COLUMN "' . $newfield . '" SET DEFAULT ' . $default);
        } else {
            pdo_query('ALTER TABLE ' . $table . ' CHANGE ' . $field . ' ' . $newfield . ' ' . $mySQLType . " DEFAULT '" . $default . "'");
            add_last_sql_error('RenameTableField');
        }
        add_log("Done renaming $field to $newfield for $table", 'RenameTableField');
    }
}

/** Return true if the given index exists for the column */
function pdo_check_index_exists($tablename, $columnname)
{
    global $CDASH_DB_TYPE;

    if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE != 'mysql') {
        echo 'NOT IMPLEMENTED';
        return false;
    } else {
        $query = pdo_query('SHOW INDEX FROM ' . $tablename . ' WHERE Seq_in_index=1');
        if ($query) {
            while ($index_array = pdo_fetch_array($query)) {
                if ($index_array['Column_name'] == $columnname) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Helper function to add an index to a table
function AddTableIndex($table, $field)
{
    include dirname(__DIR__) . '/config/config.php';

    $index_name = $field;
    // Support for multiple column indices
    if (is_array($field)) {
        $index_name = implode('_', $field);
        $field = implode(',', $field);
    }

    if (!pdo_check_index_exists($table, $field)) {
        add_log("Adding index $field to $table", 'AddTableIndex');
        if ($CDASH_DB_TYPE == 'pgsql') {
            @pdo_query("CREATE INDEX $index_name ON $table ($field)");
        } else {
            pdo_query("ALTER TABLE $table ADD INDEX $index_name ($field)");
            add_last_sql_error('AddTableIndex');
        }
        add_log("Done adding index $field to $table", 'AddTableIndex');
    }
}

// Helper function to remove an index to a table
function RemoveTableIndex($table, $field)
{
    include dirname(__DIR__) . '/config/config.php';
    if (pdo_check_index_exists($table, $field)) {
        add_log("Removing index $field from $table", 'RemoveTableIndex');

        if ($CDASH_DB_TYPE == 'pgsql') {
            pdo_query('DROP INDEX ' . $table . '_' . $field . '_idx');
        } else {
            pdo_query('ALTER TABLE ' . $table . ' DROP INDEX ' . $field);
        }
        add_log("Done removing index $field from $table", 'RemoveTableIndex');
        add_last_sql_error('RemoveTableIndex');
    }
}

// Helper function to modify a table
function ModifyTableField($table, $field, $mySQLType, $pgSqlType, $default, $notnull, $autoincrement)
{
    include dirname(__DIR__) . '/config/config.php';

    //$check = pdo_query("SELECT ".$field." FROM ".$table." LIMIT 1");
    //$type  = pdo_field_type($check,0);
    //add_log($type,"ModifyTableField");
    if (1) {
        add_log("Modifying $field to $table", 'ModifyTableField');
        if ($CDASH_DB_TYPE == 'pgsql') {
            // ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" TYPE VARCHAR( 255 );
            // ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" SET NOT NULL;
            // ALTER TABLE "dynamicanalysisdefect" ALTER COLUMN "value" SET DEFAULT 0;
            pdo_query('ALTER TABLE "' . $table . '" ALTER COLUMN  "' . $field . '" TYPE ' . $pgSqlType);
            if ($notnull) {
                pdo_query('ALTER TABLE "' . $table . '" ALTER COLUMN  "' . $field . '" SET NOT NULL');
            }
            if (strlen($default) > 0) {
                pdo_query('ALTER TABLE "' . $table . '" ALTER COLUMN  "' . $field . '" SET DEFAULT ' . $default);
            }
            if ($autoincrement) {
                pdo_query('DROP INDEX "' . $table . '_' . $field . '_idx"');
                pdo_query('ALTER TABLE "' . $table . '" ADD PRIMARY KEY ("' . $field . '")');
                pdo_query('CREATE SEQUENCE "' . $table . '_' . $field . '_seq"');
                pdo_query('ALTER TABLE  "' . $table . '" ALTER COLUMN "' . $field . "\" SET DEFAULT nextval('" . $table . '_' . $field . "_seq')");
                pdo_query('ALTER SEQUENCE "' . $table . '_' . $field . '_seq" OWNED BY "' . $table . '"."' . $field . '"');
            }
        } else {
            //ALTER TABLE dynamicanalysisdefect MODIFY value INT NOT NULL DEFAULT 0;
            $sql = 'ALTER TABLE ' . $table . ' MODIFY ' . $field . ' ' . $mySQLType;
            if ($notnull) {
                $sql .= ' NOT NULL';
            }
            if (strlen($default) > 0) {
                $sql .= " DEFAULT '" . $default . "'";
            }
            if ($autoincrement) {
                $sql .= ' AUTO_INCREMENT';
            }
            pdo_query($sql);
        }
        add_last_sql_error('ModifyTableField');
        add_log("Done modifying $field to $table", 'ModifyTableField');
    }
}

// Helper function to add an index to a table
function AddTablePrimaryKey($table, $field)
{
    include dirname(__DIR__) . '/config/config.php';
    add_log("Adding primarykey $field to $table", 'AddTablePrimaryKey');
    if ($CDASH_DB_TYPE == 'pgsql') {
        pdo_query('ALTER TABLE "' . $table . '" ADD PRIMARY KEY ("' . $field . '")');
    } else {
        pdo_query('ALTER IGNORE TABLE ' . $table . ' ADD PRIMARY KEY ( ' . $field . ' )');
    }
    //add_last_sql_error("AddTablePrimaryKey");
    add_log("Done adding primarykey $field to $table", 'AddTablePrimaryKey');
}

// Helper function to add an index to a table
function RemoveTablePrimaryKey($table)
{
    include dirname(__DIR__) . '/config/config.php';
    add_log("Removing primarykey from $table", 'RemoveTablePrimaryKey');
    if ($CDASH_DB_TYPE == 'pgsql') {
        pdo_query('ALTER TABLE "' . $table . '" DROP CONSTRAINT "value_pkey"');
        pdo_query('ALTER TABLE "' . $table . '" DROP CONSTRAINT "' . $table . '_pkey"');
    } else {
        pdo_query('ALTER TABLE ' . $table . ' DROP PRIMARY KEY');
    }
    //add_last_sql_error("RemoveTablePrimaryKey");
    add_log("Done removing primarykey from $table", 'RemoveTablePrimaryKey');
}

/** Compress the notes. Since they are almost always the same form build to build */
function CompressNotes()
{
    // Rename the old note table
    if (!pdo_query('RENAME TABLE note TO notetemp')) {
        echo pdo_error();
        echo 'Cannot rename table note to notetemp';
        return false;
    }

    // Create the new note table
    $query = 'CREATE TABLE note (
        id bigint(20) NOT NULL auto_increment,
           text mediumtext NOT NULL,
           name varchar(255) NOT NULL,
           crc32 int(11) NOT NULL,
           PRIMARY KEY  (id),
           KEY crc32 (crc32))';

    if (!pdo_query($query)) {
        echo pdo_error();
        echo "Cannot create new table 'note'";
        return false;
    }

    // Move each note from notetemp to the new table
    $note = pdo_query('SELECT * FROM notetemp ORDER BY buildid ASC');
    while ($note_array = pdo_fetch_array($note)) {
        $text = $note_array['text'];
        $name = $note_array['name'];
        $time = $note_array['time'];
        $buildid = $note_array['buildid'];
        $crc32 = crc32($text . $name);

        $notecrc32 = pdo_query("SELECT id FROM note WHERE crc32='$crc32'");
        if (pdo_num_rows($notecrc32) == 0) {
            pdo_query("INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')");
            $noteid = pdo_insert_id('note');
            echo pdo_error();
        } else {
            // already there

            $notecrc32_array = pdo_fetch_array($notecrc32);
            $noteid = $notecrc32_array['id'];
        }

        pdo_query("INSERT INTO build2note (buildid,noteid,time) VALUES ('$buildid','$noteid','$time')");
        echo pdo_error();
    }

    // Drop the old note table
    pdo_query('DROP TABLE notetemp');
    echo pdo_error();
}

/** Compute the timing for test
 *  For each test we compare with the previous build and if the percentage time
 *  is more than the project.testtimepercent we increas test.timestatus by one.
 *  We also store the test.reftime which is the time of the test passing
 *
 *  If test.timestatus is more than project.testtimewindow we reset
 *  the test.timestatus to zero and we set the test.reftime to the previous build time.
 */
function ComputeTestTiming($days = 4)
{
    // Loop through the projects
    $project = pdo_query('SELECT id,testtimestd,testtimestdthreshold FROM project');
    $weight = 0.3;

    while ($project_array = pdo_fetch_array($project)) {
        $projectid = $project_array['id'];
        $testtimestd = $project_array['testtimestd'];
        $projecttimestdthreshold = $project_array['testtimestdthreshold'];

        // only test a couple of days
        $now = gmdate(FMT_DATETIME, time() - 3600 * 24 * $days);

        // Find the builds
        $builds = pdo_query("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i = 0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $buildid = $build_array['id'];
            $buildname = $build_array['name'];
            $buildtype = $build_array['type'];
            $starttime = $build_array['starttime'];
            $siteid = $build_array['siteid'];

            // Find the previous build
            $previousbuild = pdo_query("SELECT id FROM build
                    WHERE build.siteid='$siteid'
                    AND build.type='$buildtype' AND build.name='$buildname'
                    AND build.projectid='$projectid'
                    AND build.starttime<'$starttime'
                    AND build.starttime>'$now'
                    ORDER BY build.starttime DESC LIMIT 1");

            echo pdo_error();

            // If we have one
            if (pdo_num_rows($previousbuild) > 0) {
                // Loop through the tests
                $previousbuild_array = pdo_fetch_array($previousbuild);
                $previousbuildid = $previousbuild_array ['id'];

                $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name
                        FROM build2test,test WHERE build2test.buildid='$buildid'
                        AND build2test.testid=test.id
                        ");
                echo pdo_error();

                flush();
                ob_flush();

                // Find the previous test
                $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                        WHERE build2test.buildid='$previousbuildid'
                        AND test.id=build2test.testid
                        ");
                echo pdo_error();

                $testarray = array();
                while ($test_array = pdo_fetch_array($previoustest)) {
                    $test = array();
                    $test['id'] = $test_array['testid'];
                    $test['name'] = $test_array['name'];
                    $testarray[] = $test;
                }

                while ($test_array = pdo_fetch_array($tests)) {
                    $testtime = $test_array['time'];
                    $testid = $test_array['testid'];
                    $testname = $test_array['name'];

                    $previoustestid = 0;

                    foreach ($testarray as $test) {
                        if ($test['name'] == $testname) {
                            $previoustestid = $test['id'];
                            break;
                        }
                    }

                    if ($previoustestid > 0) {
                        $previoustest = pdo_query("SELECT timemean,timestd FROM build2test
                                WHERE buildid='$previousbuildid'
                                AND build2test.testid='$previoustestid'
                                ");

                        $previoustest_array = pdo_fetch_array($previoustest);
                        $previoustimemean = $previoustest_array['timemean'];
                        $previoustimestd = $previoustest_array['timestd'];

                        // Check the current status
                        if ($previoustimestd < $projecttimestdthreshold) {
                            $previoustimestd = $projecttimestdthreshold;
                        }

                        // Update the mean and std
                        $timemean = (1 - $weight) * $previoustimemean + $weight * $testtime;
                        $timestd = sqrt((1 - $weight) * $previoustimestd * $previoustimestd + $weight * ($testtime - $timemean) * ($testtime - $timemean));

                        // Check the current status
                        if ($testtime > $previoustimemean + $testtimestd * $previoustimestd) {
                            // only do positive std

                            $timestatus = 1; // flag
                        } else {
                            $timestatus = 0;
                        }
                    } else {
                        // the test doesn't exist

                        $timestd = 0;
                        $timestatus = 0;
                        $timemean = $testtime;
                    }

                    pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                            WHERE buildid='$buildid' AND testid='$testid'");
                }
            } else {
                // this is the first build

                $timestd = 0;
                $timestatus = 0;

                // Loop throught the tests
                $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid='$buildid'");
                while ($test_array = pdo_fetch_array($tests)) {
                    $timemean = $test_array['time'];
                    $testid = $test_array['testid'];

                    pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                            WHERE buildid='$buildid' AND testid='$testid'");
                }
            } // loop through the tests

            // Progress bar
            $perc = ($i / $total) * 100;
            if ($perc - $previousperc > 5) {
                echo round($perc, 3) . '% done.<br>';
                flush();
                ob_flush();
                $previousperc = $perc;
            }
            $i++;
        }
    }
}

/** Compute the statistics for the updated file. Number of produced errors, warning, test failings. */
function ComputeUpdateStatistics($days = 4)
{
    include_once 'models/build.php';

    // Loop through the projects
    $project = pdo_query('SELECT id FROM project');

    while ($project_array = pdo_fetch_array($project)) {
        $projectid = $project_array['id'];

        // only test a couple of days
        $now = gmdate(FMT_DATETIME, time() - 3600 * 24 * $days);

        // Find the builds
        $builds = pdo_query("SELECT starttime,siteid,name,type,id
                FROM build
                WHERE build.projectid='$projectid' AND build.starttime>'$now'
                ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i = 0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $Build = new Build();
            $Build->Id = $build_array['id'];
            $Build->ProjectId = $projectid;
            $Build->ComputeUpdateStatistics();

            // Progress bar
            $perc = ($i / $total) * 100;
            if ($perc - $previousperc > 5) {
                echo round($perc, 3) . '% done.<br>';
                flush();
                ob_flush();
                $previousperc = $perc;
            }
            $i++;
        }
    }
}

/** Delete unused rows */
function delete_unused_rows($table, $field, $targettable, $selectfield = 'id')
{
    pdo_query("DELETE FROM $table WHERE $field NOT IN (SELECT $selectfield AS $field FROM $targettable)");
    echo pdo_error();
}

/** Move some columns from buildfailure to buildfailuredetails table.
 *  This function is parameterized to make it easier to test.
 **/
function UpgradeBuildFailureTable($from_table = 'buildfailure', $to_table = 'buildfailuredetails')
{
    // Check if the buildfailure table has a column named 'stdoutput'.
    // If not, we return early because this upgrade has already been performed.
    $result = pdo_query("SELECT stdoutput FROM $from_table LIMIT 1");
    if ($result === false) {
        return;
    }

    // Add the detailsid field to our buildfailure table.
    AddTableField($from_table, 'detailsid', 'bigint(20)', 'BIGINT', '0');

    // Iterate over buildfailure rows.
    // We break this up into separate queries of 5,000 each because otherwise
    // memory usage increases with each iteration of our loop.
    $count_results = pdo_single_row_query(
        "SELECT COUNT(1) AS numfails FROM $from_table");
    $numfails = intval($count_results['numfails']);
    $numconverted = 0;
    $last_id = 0;
    $stride = 5000;
    while ($numconverted < $numfails) {
        $result = pdo_query(
            "SELECT * FROM $from_table WHERE id > $last_id ORDER BY id LIMIT $stride");
        while ($row = pdo_fetch_array($result)) {
            // Compute crc32 for this buildfailure's details.
            $crc32 = crc32(
                $row['outputfile'] . $row['stdoutput'] . $row['stderror'] .
                $row['sourcefile']);

            // Get detailsid if it already exists, otherwise insert a new row.
            $details_result = pdo_single_row_query(
                "SELECT id FROM $to_table WHERE crc32=" . qnum($crc32));
            if ($details_result && array_key_exists('id', $details_result)) {
                $details_id = $details_result['id'];
            } else {
                $type = $row['type'];
                $stdoutput = pdo_real_escape_string($row['stdoutput']);
                $stderror = pdo_real_escape_string($row['stderror']);
                $exitcondition = pdo_real_escape_string($row['exitcondition']);
                $language = pdo_real_escape_string($row['language']);
                $targetname = pdo_real_escape_string($row['targetname']);
                $outputfile = pdo_real_escape_string($row['outputfile']);
                $outputtype = pdo_real_escape_string($row['outputtype']);

                $query =
                    "INSERT INTO $to_table
                    (type, stdoutput, stderror, exitcondition, language, targetname,
                     outputfile, outputtype, crc32)
                    VALUES
                    ('$type', '$stdoutput', '$stderror', '$exitcondition', '$language',
                     '$targetname', '$outputfile', '$outputtype','$crc32')";
                if (!pdo_query($query)) {
                    add_last_sql_error('UpgradeBuildFailureTable::InsertDetails', 0, $row['id']);
                }
                $details_id = pdo_insert_id($to_table);
            }

            $query =
                "UPDATE $from_table SET detailsid=" . qnum($details_id) . '
                WHERE id=' . qnum($row['id']);
            if (!pdo_query($query)) {
                add_last_sql_error('UpgradeBuildFailureTable::UpdateDetailsId', 0, $details_id);
            }
            $last_id = $row['id'];
        }
        $numconverted += $stride;
    }

    // Remove old columns from buildfailure table.
    RemoveTableField($from_table, 'type');
    RemoveTableField($from_table, 'stdoutput');
    RemoveTableField($from_table, 'stderror');
    RemoveTableField($from_table, 'exitcondition');
    RemoveTableField($from_table, 'language');
    RemoveTableField($from_table, 'targetname');
    RemoveTableField($from_table, 'outputfile');
    RemoveTableField($from_table, 'outputtype');
    RemoveTableField($from_table, 'crc32');
}

/**
 * Make sure each build has a correct value set for the
 * build.configureduration field.
 **/
function UpgradeConfigureDuration()
{
    // Do non-parent builds first.
    $query = '
        SELECT b.id, c.starttime, c.endtime
        FROM build AS b
        LEFT JOIN configure AS c ON b.id=c.buildid
        WHERE b.configureduration = 0 AND b.parentid != -1';
    $result = pdo_query($query);

    while ($row = pdo_fetch_array($result)) {
        $id = $row['id'];
        $duration = strtotime($row['endtime']) - strtotime($row['starttime']);
        if ($duration === 0) {
            continue;
        }
        $update_query =
            'UPDATE build SET configureduration=' . qnum($duration) .
            ' WHERE id=' . qnum($id);
        if (!pdo_query($update_query)) {
            add_last_sql_error('UpgradeConfigureDuration', 0, $id);
        }
    }

    // Now handle the parent builds.
    $query = '
        SELECT id FROM build
        WHERE configureduration = 0 AND parentid = -1';
    $result = pdo_query($query);

    while ($row = pdo_fetch_array($result)) {
        $id = $row['id'];
        $subquery =
            'SELECT sum(configureduration) AS configureduration
            FROM build WHERE parentid=' . qnum($id);
        $subrow = pdo_single_row_query($subquery);

        $duration = $subrow['configureduration'];
        if ($duration === 0) {
            continue;
        }

        $update_query =
            'UPDATE build SET configureduration=' . qnum($duration) .
            ' WHERE id=' . qnum($id);
        if (!pdo_query($update_query)) {
            add_last_sql_error('UpgradeConfigureDuration', 0, $id);
        }
    }
}

/**
 * Make sure each parent build has test timing set.
 **/
function UpgradeTestDuration()
{
    // Find parent builds that don't have test duration set.
    $query =
        'SELECT b.id FROM build AS b
        WHERE b.parentid=-1 AND NOT EXISTS
        (SELECT null FROM buildtesttime AS btt WHERE btt.buildid = b.id)';
    $result = pdo_query($query);

    while ($row = pdo_fetch_array($result)) {
        $id = qnum($row['id']);

        // Set the parent's test duration to be the sum of its children.
        $query =
            "SELECT sum(time) AS duration FROM buildtesttime AS btt
            INNER JOIN build AS b on (btt.buildid = b.id)
            WHERE b.parentid=$id";
        $subrow = pdo_single_row_query($query);
        $duration = qnum($subrow['duration']);

        $update_query =
            "INSERT INTO buildtesttime (buildid, time) VALUES ($id, $duration)";
        if (!pdo_query($update_query)) {
            add_last_sql_error('UpgradeTestDuration', 0, $id);
        }
    }
}

/**
 * Make sure each build has a duration.
 **/
function UpgradeBuildDuration($buildid=null)
{
    global $CDASH_DB_TYPE;
    if ($CDASH_DB_TYPE === 'pgsql') {
        $end_minus_start = 'EXTRACT(EPOCH FROM (endtime - starttime))::numeric';
    } else {
        $end_minus_start = 'TIMESTAMPDIFF(SECOND, starttime, endtime)';
    }
    $query = "UPDATE build SET buildduration = $end_minus_start
        WHERE buildduration = 0";
    if (!is_null($buildid)) {
        $query .= " AND id = $buildid";
    }
    if (!pdo_query($query)) {
        add_last_sql_error('UpgradeTestDuration');
    }
}

/** Support for compressed coverage.
 *  This is done in two steps.
 *  First step: Reducing the size of the coverage file by computing the crc32 in coveragefile
 *              and changing the appropriate fileid in coverage and coveragefilelog
 *  Second step: Reducing the size of the coveragefilelog by computing the crc32 of the groupid
 *               if the same coverage is beeing stored over and over again then it's discarded (same groupid)
 */
function CompressCoverage()
{
    /* FIRST STEP */
    // Compute the crc32 of the fullpath+file
    $coveragefile = pdo_query('SELECT count(*) AS num FROM coveragefile WHERE crc32 IS NULL');
    $coveragefile_array = pdo_fetch_array($coveragefile);
    $total = $coveragefile_array['num'];

    $i = 0;
    $previousperc = 0;
    $coveragefile = pdo_query('SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000');
    while (pdo_num_rows($coveragefile) > 0) {
        while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
            $fullpath = $coveragefile_array['fullpath'];
            $file = $coveragefile_array['file'];
            $id = $coveragefile_array['id'];
            $crc32 = crc32($fullpath . $file);
            pdo_query("UPDATE coveragefile SET crc32='$crc32' WHERE id='$id'");
        }
        $i += 1000;
        $coveragefile = pdo_query('SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000');
        $perc = ($i / $total) * 100;
        if ($perc - $previousperc > 10) {
            echo round($perc, 3) . '% done.<br>';
            flush();
            ob_flush();
            $previousperc = $perc;
        }
    }

    // Delete files with the same crc32 and upgrade
    $previouscrc32 = 0;
    $coveragefile = pdo_query('SELECT id,crc32 FROM coveragefile ORDER BY crc32 ASC,id ASC');
    $total = pdo_num_rows($coveragefile);
    $i = 0;
    $previousperc = 0;
    while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
        $id = $coveragefile_array['id'];
        $crc32 = $coveragefile_array['crc32'];
        if ($crc32 == $previouscrc32) {
            pdo_query("UPDATE coverage SET fileid='$currentid' WHERE fileid='$id'");
            pdo_query("UPDATE coveragefilelog SET fileid='$currentid' WHERE fileid='$id'");
            pdo_query("DELETE FROM coveragefile WHERE id='$id'");
        } else {
            $currentid = $id;
            $perc = ($i / $total) * 100;
            if ($perc - $previousperc > 10) {
                echo round($perc, 3) . '% done.<br>';
                flush();
                ob_flush();
                $previousperc = $perc;
            }
        }
        $previouscrc32 = $crc32;
        $i++;
    }

    /* Remove the Duplicates in the coverage section */
    $coverage = pdo_query('SELECT buildid,fileid,count(*) as cnt FROM coverage GROUP BY buildid,fileid');
    while ($coverage_array = pdo_fetch_array($coverage)) {
        $cnt = $coverage_array['cnt'];
        if ($cnt > 1) {
            $buildid = $coverage_array['buildid'];
            $fileid = $coverage_array['fileid'];
            $limit = $cnt - 1;
            $sql = "DELETE FROM coverage WHERE buildid='$buildid' AND fileid='$fileid'";
            $sql .= ' LIMIT ' . $limit;
            pdo_query($sql);
        }
    }

    /* SECOND STEP */
}

/** Carefully add a unique constraint (name & IP address) to the site table.
 *  This function is parameterized to make it easier to test.
 **/
function AddUniqueConstraintToSiteTable($site_table)
{
    global $CDASH_DB_TYPE;
    // Tables with a siteid field that will need to be updated as we prune
    // out duplicate sites.
    $tables_to_update = array('build', 'build2grouprule', 'site2user',
        'client_job', 'client_site2cmake', 'client_site2compiler',
        'client_site2library', 'client_site2program',
        'client_site2project');

    // Find all the rows that will violate this new unique constraint.
    $query = "SELECT name, ip, COUNT(*) FROM $site_table
        GROUP BY name, ip HAVING COUNT(*) > 1";
    $result = pdo_query($query);
    while ($row = pdo_fetch_array($result)) {
        $name = $row['name'];
        $ip = $row['ip'];

        // We keep the most recent, non-null value for lat & lon (if any)
        $lat_result = pdo_single_row_query(
            "SELECT id FROM $site_table
                WHERE name = '$name' AND ip = '$ip' AND latitude != '' AND
                longitude != '' ORDER BY id DESC LIMIT 1");
        if ($lat_result && array_key_exists('id', $lat_result)) {
            $id_to_keep = $lat_result['id'];
        } else {
            // Otherwise just use the row with the lowest ID.
            $id_result = pdo_single_row_query(
                "SELECT id FROM $site_table
                    WHERE name = '$name' AND ip = '$ip' ORDER BY id LIMIT 1");
            if (!$id_result || !array_key_exists('id', $id_result)) {
                continue;
            }
            $id_to_keep = $id_result['id'];
        }

        // Now that we've identified which row to keep, let's find all its
        // duplicates to remove.
        $ids_to_remove = array();
        $dupe_query =
            "SELECT id FROM $site_table
            WHERE id != $id_to_keep AND name = '$name' AND ip = '$ip'";
        $dupe_result = pdo_query($dupe_query);
        while ($dupe_row = pdo_fetch_array($dupe_result)) {
            $id_to_remove = $dupe_row['id'];
            // Update any references to this duplicate site.
            foreach ($tables_to_update as $table) {
                pdo_query("UPDATE $table SET siteid=$id_to_keep
                        WHERE siteid=$id_to_remove");
            }
            // Remove the duplicate.
            pdo_query("DELETE FROM siteinformation WHERE siteid=$id_to_remove");
            pdo_query("DELETE FROM client_jobschedule2site WHERE siteid=$id_to_remove");
            pdo_query("DELETE FROM $site_table WHERE id=$id_to_remove");
        }
    }
    // It should be safe to add the constraint now.
    if ($CDASH_DB_TYPE == 'pgsql') {
        pdo_query("ALTER TABLE $site_table ADD UNIQUE (name,ip)");
        pdo_query('CREATE INDEX "name_ip" ON "' . $site_table . '" ("name","ip")');
    } else {
        pdo_query("ALTER TABLE $site_table ADD UNIQUE KEY (name,ip)");
    }
}


/** Fix builds that performed dynamic analysis but don't have a row in the
 *  summary table.
 **/
function PopulateDynamicAnalysisSummaryTable()
{
    require_once 'models/dynamicanalysissummary.php';
    $pdo = get_link_identifier()->getPdo();

    // Find all the builds that need to have a row created in the
    // dynamicanalysissummaryrows table.
    $build_stmt = $pdo->prepare(
        'SELECT DISTINCT da.buildid, da.checker FROM dynamicanalysis AS da
        LEFT JOIN dynamicanalysissummary AS das ON (da.buildid=das.buildid)
        WHERE das.buildid IS NULL');
    $build_stmt->execute();
    while ($build_row = $build_stmt->fetch()) {
        $buildid= $build_row['buildid'];
        // Get the number of defects for this build.
        $defect_stmt = $pdo->prepare(
            'SELECT sum(b.value) AS numdefects FROM dynamicanalysis AS a
            INNER JOIN dynamicanalysisdefect AS b ON (a.id=b.dynamicanalysisid)
            WHERE a.buildid=?');
        $defect_stmt->execute(array($buildid));
        $defect_row = $defect_stmt->fetch();

        // Create the summary for this build.
        $summary = new DynamicAnalysisSummary();
        $summary->BuildId = $buildid;
        $summary->Checker = $build_row['checker'];
        $summary->AddDefects($defect_row['numdefects']);

        // Determine whether this is a parent, child, or standalone build.
        $parent_stmt = $pdo->prepare('SELECT parentid FROM build WHERE id=?');
        $parent_stmt->execute(array($buildid));
        $parent_row = $parent_stmt->fetch();
        $parentid = $parent_row['parentid'];

        // Insert the summary into the database.
        if ($parentid == 0) {
            // Standalone build.
            $summary->Insert(false);
        } elseif ($parentid == -1) {
            // Parent build.
            $summary->Insert(true);
        } else {
            // Child build.
            $summary->Insert(false);
            // Also add these defects to the parent.
            $summary->BuildId = $parentid;
            $summary->Insert(true);
        }
    }
}

// Add unique constraint (buildid and type) to the *diff tables.
function AddUniqueConstraintToDiffTables($testing=false)
{
    global $CDASH_DB_TYPE;

    $prefix = '';
    if ($testing) {
        $prefix = 'test_';
    }

    $tables = [$prefix . 'builderrordiff', $prefix . 'configureerrordiff', $prefix . 'testdiff'];

    $pdo = get_link_identifier()->getPdo();
    foreach ($tables as $table) {
        // Find all the rows that will violate this new unique constraint.
        $rows = $pdo->query(
            "SELECT buildid, type, COUNT(*) AS c FROM $table
            GROUP BY buildid, type HAVING COUNT(*) > 1");
        foreach ($rows as $row) {
            // Remove duplicates by deleting all but one row.
            $buildid = $row['buildid'];
            $type = $row['type'];
            $limit = $row['c'] - 1;
            if ($CDASH_DB_TYPE == 'pgsql') {
                // Postgres doesn't allow DELETE with LIMIT, so we use
                // ctid to get around this limitation.
                $delete_stmt = $pdo->prepare(
                    "DELETE FROM $table WHERE ctid IN
                    (SELECT ctid FROM $table
                     WHERE buildid=:buildid AND type=:type
                     LIMIT $limit)");
            } else {
                $delete_stmt = $pdo->prepare(
                    "DELETE FROM $table WHERE buildid=:buildid AND type=:type
                    LIMIT $limit");
            }
            $delete_stmt->bindParam(':buildid', $buildid);
            $delete_stmt->bindParam(':type', $type);
            $delete_stmt->execute();
        }
        // It should be safe to add the constraints now.
        if ($CDASH_DB_TYPE == 'pgsql') {
            $pdo->query("ALTER TABLE $table ADD UNIQUE (buildid,type)");
            $index_name = $table . '_buildid_type';
            $pdo->query("CREATE INDEX \"$index_name\" ON \"$table\" (\"buildid\",\"type\")");
        } else {
            $pdo->query("ALTER TABLE $table ADD UNIQUE KEY (buildid,type)");
        }
    }
}
