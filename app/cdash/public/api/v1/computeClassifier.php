<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace CDash\Api\v1\ComputeClassifier;

require_once 'include/log.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';

$builds = $_GET['builds'];
if (count($builds) < 1) {
    abort(400, 'No builds found, cannot compute classifier');
}

// Decode input data from JSON.
$builds = array_map(function ($build) {
    $decoded = json_decode($build, true);
    if ($decoded === null) {
        add_log(json_last_error_msg(), 'compute_classifier');
        return $build;
    }
    return $decoded;
}, $builds);

// Make a list of all properties defined and their values across these builds.
// allProperties = [
//   $name => [
//     'type' = number or string,
//     'values' = [list of values]
//   ]
// ]
$allProperties = [];
foreach ($builds as $build) {
    foreach ($build['properties'] as $key => $val) {
        if (!is_scalar($val)) {
            continue;
        }
        $propertyIsNumeric = false;
        if (is_float($val) || is_integer($val)) {
            $propertyIsNumeric = true;
        }

        $val = value_to_string($val);

        if (!array_key_exists($key, $allProperties)) {
            $allProperties[$key] = [];
            $allProperties[$key]['values'] = [];
            if ($propertyIsNumeric) {
                $allProperties[$key]['type'] = 'number';
            } else {
                $allProperties[$key]['type'] = 'string';
            }
        }
        if (!in_array($val, $allProperties[$key]['values'])) {
            $allProperties[$key]['values'][] = $val;
        }
    }
}

// Compute information gain for each value of each property.
$classifiers = [];
foreach ($allProperties as $propertyName => $propertyData) {
    if ($propertyData['type'] == 'number') {
        // Numerical property.
        list($classifierName, $score) =
            find_numerical_classifier($propertyName, $propertyData, $builds);
        $classifiers[] =
            ['classifier' => $classifierName, 'accuracy' => $score];
    } else {
        // Categorical property.
        foreach ($propertyData['values'] as $value) {
            $inGroup = [];
            $outGroup = [];
            foreach ($builds as $build) {
                if (array_key_exists($propertyName, $build['properties']) &&
                        value_to_string($build['properties'][$propertyName]) === $value) {
                    $inGroup[] = $build;
                } else {
                    $outGroup[] = $build;
                }
            }
            $score = compute_classifier_score($inGroup, $outGroup);
            $classifiers[] =
                ['classifier' => "$propertyName == $value",
                'accuracy' => $score];
        }
    }
}
echo json_encode(cast_data_for_JSON($classifiers));


// Convert a property value into a string.
function value_to_string($value)
{
    if (is_bool($value)) {
        $value = ($value) ? 'true' : 'false';
    } else {
        $value = (string)$value;
    }
    return $value;
}


// Count number of true and false samples in this group of builds.
function count_samples($samples)
{
    $numSucceeded = 0;
    $numFailed = 0;
    foreach ($samples as $sample) {
        if ($sample['success'] === true) {
            $numSucceeded++;
        } else {
            $numFailed++;
        }
    }
    return [$numSucceeded, $numFailed];
}


// Return as a percentage how well these two groups are classified.
function compute_classifier_score($inGroup, $outGroup)
{
    // Avoid divide-by-zero error if one of our groups contains 100% of the samples.
    if (count($inGroup) === 0 || count($outGroup) === 0) {
        return 0;
    }

    // Count number of successful and failed samples for the in & out groups.
    list($numSucceededInGroup, $numFailedInGroup) = count_samples($inGroup);
    list($numSucceededOutGroup, $numFailedOutGroup) = count_samples($outGroup);

    // We initially assume that the in group should contain true samples and the
    // out group should contain false samples.
    $numCorrect = $numSucceededInGroup + $numFailedOutGroup;
    $numMisclassified = $numFailedInGroup + $numSucceededOutGroup;
    $total = $numCorrect + $numMisclassified;
    $score = round($numCorrect / $total, 3) * 100;
    // We flip this assumption if the resulting score is less than 50%.
    if ($score < 50.0) {
        $score = 100.0 - $score;
    }

    return $score;
}


// For a given numerical property, find the threshold value that best classifies
// the builds.
function find_numerical_classifier($propertyName, $propertyData, $builds)
{
    $bestThreshold = null;
    $bestScore = -1;
    foreach ($propertyData['values'] as $value) {
        $inGroup = [];
        $outGroup = [];
        // Use this value as a threshold to split the "in" and "out" groups.
        foreach ($builds as $build) {
            if (array_key_exists($propertyName, $build['properties']) &&
                    $build['properties'][$propertyName] <= $value) {
                $inGroup[] = $build;
            } else {
                $outGroup[] = $build;
            }
        }
        $score = compute_classifier_score($inGroup, $outGroup);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestThreshold = $value;
        }
    }
    return ["$propertyName > $bestThreshold", $bestScore];
}
