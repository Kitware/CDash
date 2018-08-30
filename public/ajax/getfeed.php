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

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include 'include/version.php';
$noforcelogin = 1;
include 'public/login.php';

use CDash\Model\Feed;

$projectid = pdo_real_escape_numeric($_GET['projectid']);
if (!isset($projectid) || !is_numeric($projectid)) {
    return;
}

$feed = new Feed();
checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

// Return when the feed was seen
function get_elapsed_time($date)
{
    $lastpingtime = '';
    $diff = time() - strtotime($date . ' UTC');
    $days = $diff / (3600 * 24);
    if (floor($days) > 0) {
        $lastpingtime .= floor($days) . ' days ';
        $diff = $diff - (floor($days) * 3600 * 24);
        return $lastpingtime;
    }
    $hours = $diff / (3600);
    if (floor($hours) > 0) {
        $lastpingtime .= floor($hours) . ' hours ';
        $diff = $diff - (floor($hours) * 3600);
        return $lastpingtime;
    }
    $minutes = $diff / (60);
    if ($minutes > 0) {
        $lastpingtime .= floor($minutes) . ' minutes';
    }

    return $lastpingtime;
} // end function

// Returns the feed type
function get_feed_type($type)
{
    switch ($type) {
        case Feed::TypeUnknown:
            return 'NA';
        case Feed::TypeUpdate:
            return 'UPDATE';
        case Feed::TypeBuildError:
            return 'BUILD ERROR';
        case Feed::TypeBuildWarning:
            return 'BUILD WARNING';
        case Feed::TypeTestPassing:
            return 'TEST PASSING';
        case Feed::TypeTestFailing:
            return 'TEST FAILING';
    }
    return 'NA';
}

// Returns the feed link
function get_feed_link($type, $buildid, $description)
{
    if ($type == Feed::TypeUpdate) {
        return '<a href="viewUpdate.php?buildid=' . $buildid . '">' . $description . '</a>';
    } elseif ($type == Feed::TypeBuildError) {
        return '<a href="viewBuildError.php?buildid=' . $buildid . '">' . $description . '</a>';
    } elseif ($type == Feed::TypeBuildWarning) {
        return '<a href="viewBuildError.php?type=1&buildid=' . $buildid . '">' . $description . '</a>';
    } elseif ($type == Feed::TypeTestPassing) {
        return '<a href="viewTest.php?onlypassed&buildid=' . $buildid . '">' . $description . '</a>';
    } elseif ($type == Feed::TypeTestFailing) {
        return '<a href="viewTest.php?onlyfailed&buildid=' . $buildid . '">' . $description . '</a>';
    } elseif ($type == Feed::TypeTestNotRun) {
        return '<a href="viewTest.php?onlynotrun&buildid=' . $buildid . '">' . $description . '</a>';
    }
    return '';
}

$feeds = $feed->GetFeed($projectid, 5); // display the last five submissions
foreach ($feeds as $f) {
    ?>
    <?php
    $elapsedtime = get_elapsed_time($f['date']);
    if ($elapsedtime == '') {
        $elapsedtime = 'Some time';
    }
    if ($elapsedtime == '0m') {
        echo 'Just now: ';
    } else {
        echo '<b>' . $elapsedtime . ' ago: </b>';
    } ?>
    <?php //echo get_feed_type($f["type"])?>
    <?php echo get_feed_link($f['type'], $f['buildid'], $f['description']); ?>
    <br/>
    <?php
} ?>
<?php if (count($feeds) > 0) {
        ?>
    <div id="feedmore"><a href="viewFeed.php?projectid=<?php echo $projectid; ?>">See full feed</a></div>
    <?php
    } ?>
