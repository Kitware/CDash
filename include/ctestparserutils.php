<?php
/** Add a new build */
function add_build($build, $clientscheduleid = 0)
{
    require_once 'models/buildgroup.php';
    if (!is_numeric($build->ProjectId) || !is_numeric($build->SiteId)) {
        return;
    }

    $buildid = $build->GetIdFromName($build->SubProjectName);
    if ($buildid > 0 && !$build->Append) {
        $build->Id = $buildid;
        if ($build->GetDone()) {
            remove_build($buildid);
            $build->Id = null;
        }
    }

    // Move this into a Build::SetAppend($append, $buildid) method:
    //
    if (!$build->Exists() && $build->Append && empty($build->Id)) {
        $build->Id = $buildid;
    }

    // Find the groupid
    $buildGroup = new BuildGroup();
    $build->GroupId = $buildGroup->GetGroupIdFromRule($build);

    $build->Save();

    // If the build is part of a subproject we link the update file
    if (isset($build->SubProjectName) && $build->SubProjectName != '') {
        require_once 'models/buildupdate.php';
        $BuildUpdate = new BuildUpdate();
        $BuildUpdate->BuildId = $build->Id;
        $BuildUpdate->AssociateBuild($build->SiteId, $build->Name, $build->GetStamp());
    }

    if ($clientscheduleid != 0) {
        require_once 'models/clientjobschedule.php';
        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->Id = $clientscheduleid;
        $ClientJobSchedule->AssociateBuild($build->Id);
    }
    return $build->Id;
}

/** Extract the type from the build stamp */
function extract_type_from_buildstamp($buildstamp)
{
    // We assume that the time stamp is always of the form
    // 20080912-1810-this-is-a-type
    return substr($buildstamp, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1) + 1);
}

/** Extract the date from the build stamp */
function extract_date_from_buildstamp($buildstamp)
{
    return substr($buildstamp, 0, strrpos($buildstamp, '-'));
}

/** Return timestamp from string
 *  \WARNING this function needs improvement */
function str_to_time($str, $stamp)
{
    $str = str_replace('Eastern Standard Time', 'EST', $str);
    $str = str_replace('Eastern Daylight Time', 'EDT', $str);

    // For some reasons the Australian time is not recognized by php
    // Actually an open bug in PHP 5.
    $offset = 0; // no offset by default
    if (strpos($str, 'AEDT') !== false) {
        $str = str_replace('AEDT', 'UTC', $str);
        $offset = 3600 * 11;
    } // We had more custom dates
    elseif (strpos($str, 'Paris, Madrid') !== false) {
        $str = str_replace('Paris, Madrid', 'UTC', $str);
        $offset = 3600 * 1;
    } elseif (strpos($str, 'W. Europe Standard Time') !== false) {
        $str = str_replace('W. Europe Standard Time', 'UTC', $str);
        $offset = 3600 * 1;
    }

    // The year is always at the end of the string if it exists (from CTest)
    $stampyear = substr($stamp, 0, 4);
    $year = substr($str, strlen($str) - 4, 2);

    if ($year != '19' && $year != '20') {
        // No year is defined we add it
        // find the hours
        $pos = strpos($str, ':');
        if ($pos !== false) {
            $tempstr = $str;
            $str = substr($tempstr, 0, $pos - 2);
            $str .= $stampyear . ' ' . substr($tempstr, $pos - 2);
        }
    }

    $strtotimefailed = 0;

    if (strtotime($str) === false) {
        $strtotimefailed = 1;
    }

    // If it's still failing we assume GMT and put the year at the end
    if ($strtotimefailed) {
        // find the hours
        $pos = strpos($str, ':');
        if ($pos !== false) {
            $tempstr = $str;
            $str = substr($tempstr, 0, $pos - 2);
            $str .= substr($tempstr, $pos - 2, 5);
        }
    }
    return strtotime($str) - $offset;
}

/** Add the difference between the numbers of errors and warnings
 *  for the previous and current build */
function compute_error_difference($buildid, $previousbuildid, $warning)
{
    // Look at the difference positive and negative test errors
    $sqlquery = 'UPDATE builderror SET newstatus=1 WHERE buildid=' . $buildid . ' AND type=' . $warning . ' AND crc32 IN
        (SELECT crc32 FROM (SELECT crc32 FROM builderror WHERE buildid=' . $buildid . '
                            AND type=' . $warning . ') AS builderrora
         LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=' . $previousbuildid . '
             AND type=' . $warning . ') AS builderrorb ON builderrora.crc32=builderrorb.crc32b
         WHERE builderrorb.crc32b IS NULL)';
    pdo_query($sqlquery);
    add_last_sql_error('compute_error_difference', 0, $buildid);

    // Recurring buildfailures are represented by the buildfailuredetails table.
    // Get a list of buildfailuredetails IDs for the current build and the
    // previous build.
    $current_failures = array();
    $previous_failures = array();

    $query =
        "SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=$buildid AND bfd.type=$warning";
    $result = pdo_query($query);
    add_last_sql_error('compute_error_difference', 0, $buildid);
    while ($row = pdo_fetch_array($result)) {
        $current_failures[] = $row['detailsid'];
    }

    $query =
        "SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=$previousbuildid AND bfd.type=$warning";
    $result = pdo_query($query);
    add_last_sql_error('compute_error_difference', 0, $buildid);
    while ($row = pdo_fetch_array($result)) {
        $previous_failures[] = $row['detailsid'];
    }

    // Check if any of these are new failures and mark them accordingly.
    foreach ($current_failures as $failure) {
        if (!in_array($failure, $previous_failures)) {
            $query =
                "UPDATE buildfailure SET newstatus=1
                WHERE buildid=$buildid AND detailsid=$failure";
            pdo_query($query);
            add_last_sql_error('compute_error_difference', 0, $buildid);
        }
    }

    // Maybe we can get that from the query (don't know).
    $positives = pdo_query('SELECT count(*) FROM builderror WHERE buildid=' . $buildid . ' AND type=' . $warning . ' AND newstatus=1');
    $positives_array = pdo_fetch_array($positives);
    $npositives = $positives_array[0];
    $positives = pdo_query(
        "SELECT COUNT(*) FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=$buildid AND bfd.type=$warning AND bf.newstatus=1");
    $positives_array = pdo_fetch_array($positives);
    $npositives += $positives_array[0];

    // Count how many build defects were fixed since the previous build.
    $sqlquery = 'SELECT count(*)
        FROM (SELECT crc32 FROM builderror WHERE buildid=' . $previousbuildid . '
                AND type=' . $warning . ') AS builderrora
        LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=' . $buildid . '
                AND type=' . $warning . ') AS builderrorb
        ON builderrora.crc32=builderrorb.crc32b WHERE builderrorb.crc32b IS NULL';
    $negatives = pdo_query($sqlquery);
    $negatives_array = pdo_fetch_array($negatives);
    $nnegatives = $negatives_array[0];

    foreach ($previous_failures as $failure) {
        if (!in_array($failure, $current_failures)) {
            $nnegatives += 1;
        }
    }

    // Don't log if no diff
    if ($npositives != 0 || $nnegatives != 0) {
        // Check if it exists
        $query = pdo_query('SELECT count(buildid) FROM builderrordiff WHERE buildid=' . qnum($buildid) . ' AND type=' . $warning);
        $query_array = pdo_fetch_array($query);

        if ($query_array[0] == 0) {
            pdo_query("INSERT INTO builderrordiff (buildid,type,difference_positive,difference_negative)
                    VALUES('$buildid','$warning','$npositives','$nnegatives')");
        } else {
            pdo_query("UPDATE builderrordiff SET difference_positive='" . $npositives . "',
                    difference_negative='" . $nnegatives . "' WHERE buildid=" . qnum($buildid) . ' AND type=' . $warning);
        }
        add_last_sql_error('compute_error_difference', 0, $buildid);
    }
}

/** Add the difference between the numbers of configure warnings
 *  for the previous and current build */
function compute_configure_difference($buildid, $previousbuildid, $warning)
{
    $pdo = get_link_identifier()->getPdo();

    // Compute the difference in the number of configure warnings/errors
    // between this build and the previous one.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM configureerror WHERE type=? AND buildid=?');

    $stmt->execute(array($warning, $buildid));
    $row = $stmt->fetch();
    $nerrors = $row[0];

    $stmt->execute(array($warning, $previousbuildid));
    $row = $stmt->fetch();
    $npreviouserrors = $row[0];

    // Don't log if no diff.
    $errordiff = $nerrors - $npreviouserrors;
    if ($errordiff != 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO configureerrordiff (buildid, type, difference)
            VALUES(?, ?, ?)');
        $stmt->execute(array($buildid, $warning, $errordiff));
        add_last_sql_error('compute_configure_difference', 0, $buildid);
    }
}

/** Add the difference between the numbers of tests
 *  for the previous and current build */
function compute_test_difference($buildid, $previousbuildid, $testtype, $projecttestmaxstatus)
{
    $sql = '';
    if ($testtype == 0) {
        $status = "'notrun'";
    } elseif ($testtype == 1) {
        $status = "'failed'";
    } elseif ($testtype == 2) {
        $status = "'passed'";
    } elseif ($testtype == 3) {
        $status = "'passed'";
        $sql = ' AND timestatus>' . $projecttestmaxstatus;
    }

    // Look at the difference positive and negative test errors
    $sqlquery = 'UPDATE build2test SET newstatus=1 WHERE buildid=' . $buildid . ' AND testid IN
        (SELECT testid FROM (SELECT test.id AS testid,name FROM build2test,test WHERE build2test.buildid=' . $buildid . '
                             AND build2test.testid=test.id AND build2test.status=' . $status . $sql . ') AS testa
         LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=' . $previousbuildid . '
             AND build2test.testid=test.id AND build2test.status=' . $status . $sql . ')
         AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL)';
    pdo_query($sqlquery);
    add_last_sql_error('compute_test_difference', 0, $buildid);

    // Maybe we can get that from the query (don't know).
    $positives = pdo_query('SELECT count(*) FROM build2test WHERE buildid=' . $buildid . ' AND newstatus=1 AND status=' . $status . $sql);
    $positives_array = pdo_fetch_array($positives);
    $npositives = $positives_array[0];

    // Count the difference between the number of tests that were passing (or failing)
    // and now that have a different one
    $sqlquery = 'SELECT count(*)
        FROM (SELECT name FROM build2test,test WHERE build2test.buildid=' . $previousbuildid . '
                AND build2test.testid=test.id AND build2test.status=' . $status . $sql . ') AS testa
        LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=' . $buildid . '
                AND build2test.testid=test.id AND build2test.status=' . $status . $sql . ')
        AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL';

    $negatives = pdo_query($sqlquery);
    $negatives_array = pdo_fetch_array($negatives);
    $nnegatives = $negatives_array[0];

    // Don't log if no diff
    if ($npositives != 0 || $nnegatives != 0) {
        // Check that we don't have any duplicates (this messes up the first page)
        $query = pdo_query('SELECT count(*) FROM testdiff WHERE buildid=' . qnum($buildid) . 'AND type=' . qnum($testtype));
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            pdo_query('UPDATE testdiff SET difference_positive=' . qnum($npositives) . ',difference_negative=' . qnum($nnegatives) . '
                    WHERE buildid=' . qnum($buildid) . 'AND type=' . qnum($testtype));
        } else {
            pdo_query("INSERT INTO testdiff (buildid,type,difference_positive,difference_negative)
                    VALUES('$buildid','$testtype','$npositives','$nnegatives')");
        }
        add_last_sql_error('compute_test_difference', 0, $buildid);
    }
}
