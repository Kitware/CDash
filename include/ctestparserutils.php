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
    return substr($buildstamp, 0, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1));
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
    $pdo = get_link_identifier()->getPdo();
    // Look at the difference positive and negative test errors
    $stmt = $pdo->prepare(
        'UPDATE builderror SET newstatus=1
        WHERE buildid=:buildid AND type=:type AND crc32 IN
        (SELECT crc32 FROM
        (SELECT crc32 FROM builderror WHERE buildid=:buildid AND type=:type)
        AS builderrora
        LEFT JOIN
        (SELECT crc32 as crc32b FROM builderror
        WHERE buildid=:previousbuildid AND type=:type)
        AS builderrorb ON builderrora.crc32=builderrorb.crc32b
        WHERE builderrorb.crc32b IS NULL)');
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':type', $warning);
    $stmt->bindValue(':previousbuildid', $previousbuildid);
    pdo_execute($stmt);

    // Recurring buildfailures are represented by the buildfailuredetails table.
    // Get a list of buildfailuredetails IDs for the current build and the
    // previous build.
    $current_failures = array();
    $previous_failures = array();

    $stmt = $pdo->prepare(
        'SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:buildid AND bfd.type=:type');
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':type', $warning);
    pdo_execute($stmt);
    while ($row = $stmt->fetch()) {
        $current_failures[] = $row['detailsid'];
    }

    $stmt = $pdo->prepare(
        'SELECT bf.detailsid FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:previousbuildid AND bfd.type=:type');
    $stmt->bindValue(':previousbuildid', $previousbuildid);
    $stmt->bindValue(':type', $warning);
    pdo_execute($stmt);
    while ($row = $stmt->fetch()) {
        $previous_failures[] = $row['detailsid'];
    }

    // Check if any of these are new failures and mark them accordingly.
    foreach ($current_failures as $failure) {
        if (!in_array($failure, $previous_failures)) {
            $stmt = $pdo->prepare(
                'UPDATE buildfailure SET newstatus=1
                WHERE buildid=:buildid AND detailsid=:detailsid');
            $stmt->bindValue(':buildid', $buildid);
            $stmt->bindValue(':detailsid', $failure);
            pdo_execute($stmt);
        }
    }

    // Maybe we can get that from the query (don't know).
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM builderror
        WHERE buildid=:buildid AND type=:type AND newstatus=1');
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':type', $warning);
    pdo_execute($stmt);
    $positives_array = $stmt->fetch();
    $npositives = $positives_array[0];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM buildfailure AS bf
        LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
        WHERE bf.buildid=:buildid AND bfd.type=:type AND bf.newstatus=1');
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':type', $warning);
    pdo_execute($stmt);
    $positives_array = $stmt->fetch();
    $npositives += $positives_array[0];

    // Count how many build defects were fixed since the previous build.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM
        (SELECT crc32 FROM builderror WHERE buildid=:previousbuildid AND type=:type) AS builderrora
        LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=:buildid AND type=:type) AS builderrorb
        ON builderrora.crc32=builderrorb.crc32b
        WHERE builderrorb.crc32b IS NULL');
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':previousbuildid', $previousbuildid);
    $stmt->bindValue(':type', $warning);
    pdo_execute($stmt);
    $negatives_array = $stmt->fetch();
    $nnegatives = $negatives_array[0];

    foreach ($previous_failures as $failure) {
        if (!in_array($failure, $current_failures)) {
            $nnegatives += 1;
        }
    }

    // Check if a diff already exists for this build.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT * FROM builderrordiff WHERE buildid=:buildid AND type=:type FOR UPDATE');
    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':type', $warning);
    pdo_execute($stmt);
    $row = $stmt->fetch();
    $existing_npositives = 0;
    $existing_nnegatives = 0;
    if ($row) {
        $existing_npositives = $row['difference_positive'];
        $existing_nnegatives = $row['difference_negative'];
    }

    // Only log if there's a diff since last build or an existing diff record.
    if ($npositives == 0 && $nnegatives == 0 && $existing_npositives == 0 && $existing_nnegatives == 0) {
        $pdo->commit();
        return;
    }

    if ($row) {
        // Update existing record.
        $stmt = $pdo->prepare(
            'UPDATE builderrordiff
            SET difference_positive=:npositives, difference_negative=:nnegatives
            WHERE buildid=:buildid AND type=:type');
    } else {
        // Insert new record.
        $duplicate_sql = '';
        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE !== 'pgsql') {
            $duplicate_sql =
                'ON DUPLICATE KEY UPDATE difference_positive=:npositives, difference_negative=:nnegatives';
        }
        $stmt = $pdo->prepare(
            "INSERT INTO builderrordiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (:buildid, :type, :npositives, :nnegatives)
            $duplicate_sql");
    }
    $stmt->bindValue(':buildid', $buildid);
    $stmt->bindValue(':type', $warning);
    $stmt->bindValue(':npositives', $npositives);
    $stmt->bindValue(':nnegatives', $nnegatives);
    if (!pdo_execute($stmt)) {
        $pdo->rollBack();
        return;
    }
    $pdo->commit();
}

/** Add the difference between the numbers of tests
 *  for the previous and current build */
function compute_test_difference($buildid, $previousbuildid, $testtype, $projecttestmaxstatus)
{
    $pdo = get_link_identifier()->getPdo();
    $extra_sql = '';
    if ($testtype == 0) {
        $status = 'notrun';
    } elseif ($testtype == 1) {
        $status = 'failed';
    } elseif ($testtype == 2) {
        $status = 'passed';
    } elseif ($testtype == 3) {
        $status = 'passed';
        $extra_sql = " AND timestatus > $projecttestmaxstatus";
    }

    // Look at the difference positive and negative test errors
    $stmt = $pdo->prepare(
        "UPDATE build2test SET newstatus=1 WHERE buildid=:buildid AND testid IN
        (SELECT testid FROM (SELECT test.id AS testid,name FROM build2test,test WHERE build2test.buildid=:buildid
                             AND build2test.testid=test.id AND build2test.status=:status $extra_sql) AS testa
         LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=:previousbuildid
             AND build2test.testid=test.id AND build2test.status=:status $extra_sql)
         AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL)");
    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':previousbuildid', $previousbuildid);
    $stmt->bindParam(':status', $status);
    pdo_execute($stmt);

    // Maybe we can get that from the query (don't know).
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM build2test WHERE buildid=:buildid AND newstatus=1 AND status=:status $extra_sql");
    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':status', $status);
    pdo_execute($stmt);
    $row = $stmt->fetch();
    $npositives = $row[0];

    // Count the difference between the number of tests that were passing (or failing)
    // and now that have a different one
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM
            (SELECT name FROM build2test,test WHERE build2test.buildid=:previousbuildid AND
             build2test.testid=test.id AND build2test.status=:status $extra_sql) AS testa
        LEFT JOIN (SELECT name as name2 FROM build2test,test WHERE build2test.buildid=:buildid
                AND build2test.testid=test.id AND build2test.status=:status $extra_sql)
        AS testb ON testa.name=testb.name2 WHERE testb.name2 IS NULL");
    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':previousbuildid', $previousbuildid);
    $stmt->bindParam(':status', $status);
    pdo_execute($stmt);
    $row = $stmt->fetch();
    $nnegatives = $row[0];

    // Check that we don't have any duplicates (this messes up index.php).
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT * FROM testdiff WHERE buildid=:buildid AND type=:type FOR UPDATE');
    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':type', $testtype);
    pdo_execute($stmt);
    $row = $stmt->fetch();

    $existing_npositives = 0;
    $existing_nnegatives = 0;
    if ($row) {
        $existing_npositives = $row['difference_positive'];
        $existing_nnegatives = $row['difference_negative'];
    }

    // Don't log if no diff.
    if ($npositives == 0 && $nnegatives == 0 && $existing_npositives == 0 && $existing_nnegatives == 0) {
        $pdo->commit();
        return;
    }

    if ($row) {
        // Update existing record.
        $stmt = $pdo->prepare(
            'UPDATE testdiff
            SET difference_positive=:npositives, difference_negative=:nnegatives
            WHERE buildid=:buildid AND type=:type');
    } else {
        // Insert new record.
        $duplicate_sql = '';
        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE !== 'pgsql') {
            $duplicate_sql =
                'ON DUPLICATE KEY UPDATE difference_positive=:npositives, difference_negative=:nnegatives';
        }
        $stmt = $pdo->prepare(
            "INSERT INTO testdiff
            (buildid, type, difference_positive, difference_negative)
            VALUES (:buildid, :type, :npositives, :nnegatives)
            $duplicate_sql");
    }

    $stmt->bindParam(':buildid', $buildid);
    $stmt->bindParam(':type', $testtype);
    $stmt->bindParam(':npositives', $npositives);
    $stmt->bindParam(':nnegatives', $nnegatives);
    if (!pdo_execute($stmt)) {
        $pdo->rollBack();
        return;
    }
    $pdo->commit();
}
