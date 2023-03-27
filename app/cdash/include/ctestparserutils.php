<?php

use CDash\Model\BuildUpdate;

/** Add a new build */
function add_build($build)
{
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

    $build->Save();

    // If the build is part of a subproject we link the update file
    if (isset($build->SubProjectName) && $build->SubProjectName != '') {
        $BuildUpdate = new BuildUpdate();
        $BuildUpdate->BuildId = $build->Id;
        $BuildUpdate->AssociateBuild($build->SiteId, $build->Name, $build->GetStamp());
    }

    return $build->Id;
}

/** Extract the type from the build stamp */
function extract_type_from_buildstamp($buildstamp)
{
    // We assume that the time stamp is always of the form
    // 20080912-1810-this-is-a-type
    if (!empty($buildstamp)) {
        return substr($buildstamp, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1) + 1);
    }
}

/** Extract the date from the build stamp */
function extract_date_from_buildstamp($buildstamp)
{
    return substr($buildstamp, 0, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1));
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
        if (config('database.default') !== 'pgsql') {
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
