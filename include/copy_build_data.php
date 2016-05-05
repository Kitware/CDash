<?php
/*=========================================================================
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

/**
 * Entry point function.
 * When you add support for a new type of submission, make sure that
 * it is handled in the switch statement below.
 **/
function copy_build_data($old_buildid, $new_buildid, $type)
{
    switch ($type) {
        case 'GcovTar':
        case 'JavaJSONTar':
            copy_coverage_data($old_buildid, $new_buildid);
    }
}

/**
 * Copy coverage info from one build to another.
 **/
function copy_coverage_data($old_buildid, $new_buildid)
{
    require_once('models/coveragefilelog.php');
    require_once('models/coveragesummary.php');

    $tables_to_copy = array(
        'coverage', 'coveragefilelog', 'coveragesummary');

    foreach ($tables_to_copy as $table) {
        copy_build_table($old_buildid, $new_buildid, $table);
    }

    // Compute difference in coverage from previous build.
    $summary = new CoverageSummary();
    $summary->BuildId = $new_buildid;
    $summary->ComputeDifference();

    // Update parent build (if any).
    $summary->Load();
    $summary->UpdateParent();

    // Update aggregate coverage if this is a nightly build.
    $row = pdo_single_row_query(
            "SELECT type FROM build WHERE id='$new_buildid'");
    if ($row['type'] === 'Nightly') {
        $result = pdo_query("
                SELECT fileid FROM coveragefilelog
                WHERE buildid='$new_buildid'");
        while ($result_array = pdo_fetch_array($result)) {
            $log = new CoverageFileLog();
            $log->BuildId = $new_buildid;
            $log->FileId = $result_array['fileid'];
            $log->Load();
            $log->UpdateAggregate();
        }
    }
}

/**
 * Copy table rows by assigning them a new buildid.
 **/
function copy_build_table($old_buildid, $new_buildid, $table)
{
    $result = pdo_query("SELECT * FROM $table WHERE buildid=$old_buildid");
    while ($result_array = pdo_fetch_array($result)) {
        // Remove the old buildid from our SELECT results, as we will be replacing
        // that with the new buildid.
        unset($result_array['buildid']);

        // Generate an INSERT query by listing all of the table columns
        // and their values.  This is slightly complicated by the fact that
        // our array has both string and integer keys.
        $keys = array();
        $values = array();
        foreach ($result_array as $key => $val) {
            if (!is_int($key)) {
                $keys[] = $key;
                $values[] = $val;
            }
        }
        $insert_query = "INSERT INTO $table (buildid,";
        $insert_query .= implode(',', $keys) . ')';
        $insert_query .= " VALUES ('$new_buildid','";
        $insert_query .= implode("','", $values) . "')";
        pdo_query($insert_query);
    }
}
