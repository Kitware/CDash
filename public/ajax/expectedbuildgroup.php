<html>
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
require_once 'include/common.php';

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$siteid = pdo_real_escape_numeric($_GET['siteid']);
$buildname = htmlspecialchars(pdo_real_escape_string($_GET['buildname']));
$buildtype = htmlspecialchars(pdo_real_escape_string($_GET['buildtype']));
$buildgroupid = pdo_real_escape_numeric($_GET['buildgroup']);
$divname = htmlspecialchars(pdo_real_escape_string($_GET['divname']));
if (!isset($siteid) || !is_numeric($siteid)) {
    echo 'Not a valid siteid!';
    return;
}

@$submit = $_POST['submit'];

@$groupid = $_POST['groupid'];
if ($groupid != null) {
    $groupid = pdo_real_escape_numeric($groupid);
}

@$expected = $_POST['expected'];
@$markexpected = $_POST['markexpected'];
@$previousgroupid = $_POST['previousgroupid'];

if ($markexpected) {
    if (!isset($groupid) || !is_numeric($groupid)) {
        echo 'Not a valid groupid!';
        return;
    }

    $expected = pdo_real_escape_string($expected);
    $markexpected = pdo_real_escape_string($markexpected);

    // If a rule already exists we update it
    pdo_query("UPDATE build2grouprule SET expected='$expected' WHERE groupid='$groupid' AND buildtype='$buildtype'
                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");
    return;
}

if ($submit) {
    // Mark any previous rule as done
    /*$now = gmdate(FMT_DATETIME);
    pdo_query("UPDATE build2grouprule SET endtime='$now'
                 WHERE groupid='$previousgroupid' AND buildtype='$buildtype'
                 AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");*/
    if (!isset($previousgroupid) || !is_numeric($previousgroupid)) {
        echo 'Not a valid previousgroupid!';
        return;
    }

    // Delete the previous rule for that build
    pdo_query("DELETE FROM build2grouprule  WHERE groupid='$previousgroupid' AND buildtype='$buildtype'
               AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00'");

    // Add the new rule (begin time is set by default by mysql
    pdo_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime,endtime)
               VALUES ('$groupid','$buildtype','$buildname','$siteid','$expected','1980-01-01 00:00:00','1980-01-01 00:00:00')");

    // Move any builds that follow this rule to the correct build2group
    $buildgroups = pdo_query('SELECT * from build2group');
    while ($buildgroup_array = pdo_fetch_array($buildgroups)) {
        $buildid = $buildgroup_array['buildid'];

        $build = pdo_query("SELECT * from build WHERE id='$buildid'");
        $build_array = pdo_fetch_array($build);
        $type = $build_array['type'];
        $name = $build_array['name'];
        $siteid = $build_array['siteid'];
        $projectid = $build_array['projectid'];
        $submittime = $build_array['submittime'];

        $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
        echo pdo_error();
        if (pdo_num_rows($build2grouprule) > 0) {
            $build2grouprule_array = pdo_fetch_array($build2grouprule);
            $groupid = $build2grouprule_array['groupid'];
            pdo_query("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
        }
    }
    return;
}

if (!isset($buildgroupid) || !is_numeric($buildgroupid)) {
    echo 'Invalid buildgroupid';
    return;
}

// Find the project variables
$currentgroup = pdo_query("SELECT id,name,projectid FROM buildgroup WHERE id='$buildgroupid'");
$currentgroup_array = pdo_fetch_array($currentgroup);
$projectid = $currentgroup_array['projectid'];

// Find the groups available for this project
$group = pdo_query("SELECT name,id FROM buildgroup WHERE id!='$buildgroupid' AND projectid='$projectid'");
?>

<script type="text/javascript" charset="utf-8">
    function URLencode(sStr) {
        return escape(sStr)
            .replace(/\+/g, '%2B')
            .replace(/\"/g, '%22')
            .replace(/\'/g, '%27');
    }

    function markasnonexpected_click(siteid, buildname, buildtype, groupid, expected, divname) {
        var group = "#infoexpected_" + divname;
        $(group).html("updating...");

        buildname = URLencode(buildname);
        $.post("ajax/expectedbuildgroup.php?siteid=" + siteid + "&buildname=" + buildname + "&buildtype=" + buildtype + "&divname=" + divname,
            {markexpected: "1", groupid: groupid, expected: expected},
            function (data) {
                $(group).html("updated!");
                $(group).fadeOut('slow');
                window.location = "";
            }
        );
    }

    function movenonexpectedbuildgroup_click(siteid, buildname, buildtype, groupid, previousgroupid, divname, expectedtag) {
        var tag = "expectednosubmission_" + expectedtag;
        var t = document.getElementById(tag);
        var expectedbuild = 0;
        if (t.checked) {
            expectedbuild = 1;
        }

        buildname = URLencode(buildname);

        var group = "#infoexpected_" + divname;
        $(group).html("addinggroup");
        $.post("ajax/expectedbuildgroup.php?siteid=" + siteid + "&buildname=" + buildname + "&buildtype=" + buildtype + "&divname=" + divname, {
            submit: "1",
            groupid: groupid,
            expected: expectedbuild,
            previousgroupid: previousgroupid
        });
        $.post("ajax/expectedbuildgroup.php?siteid=" + siteid + "&buildname=" + buildname + "&buildtype=" + buildtype + "&divname=" + divname,
            {submit: "1", groupid: groupid, expected: expectedbuild, previousgroupid: previousgroupid},
            function (data) {
                $(group).html("added to group!");
                $(group).fadeOut('slow');
                window.location = "";
            });
    }
</script>
<form method="post" action="">

    <table width="100%" border="0">
        <tr>
            <?php
            // If expected
            // Find the groups available for this project
            $isexpected = 0;
            $currentgroupid = $currentgroup_array['id'];

            // This works only for the most recent dashboard (and future)
            $build2groupexpected = pdo_query("SELECT groupid FROM build2grouprule WHERE groupid='$currentgroupid' AND buildtype='$buildtype'
                                      AND buildname='$buildname' AND siteid='$siteid' AND endtime='1980-01-01 00:00:00' AND expected='1'");
            if (pdo_num_rows($build2groupexpected) > 0) {
                $isexpected = 1;
            }
            ?>
            <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $currentgroup_array['name'] ?></b>: </font>
            </td>
            <td bgcolor="#DDDDDD" width="65%" colspan="2" id="nob"><font size="2"><a href="#"
                                                                                     onclick="javascript:markasnonexpected_click('<?php echo $siteid ?>','<?php echo $buildname ?>','<?php echo $buildtype ?>','<?php echo $currentgroup_array['id'] ?>',
                                                                                     <?php if ($isexpected) {
                echo '0';
            } else {
                echo '1';
            } ?>,'<?php echo $divname ?>')">
                        [<?php
                        if ($isexpected) {
                            echo 'mark as non expected';
                        } else {
                            echo 'mark as expected';
                        }

                        ?>]</a> </font></td>
        </tr>
        <?php
        while ($group_array = pdo_fetch_array($group)) {
            ?>
            <tr>
                <td bgcolor="#DDDDDD" width="35%"><font size="2"><b><?php echo $group_array['name'] ?></b>: </font></td>
                <td bgcolor="#DDDDDD" width="20%"><font size="2"><input
                            id="expectednosubmission_<?php $expectedtag = rand();
            echo $expectedtag; ?>" type="checkbox"/> expected</font></td>
                <td bgcolor="#DDDDDD" width="45%" id="nob"><font size="2">
                        <a href="#"
                           onclick="javascript:movenonexpectedbuildgroup_click('<?php echo $siteid ?>','<?php echo $buildname ?>','<?php echo $buildtype ?>','<?php echo $group_array['id'] ?>','<?php echo $currentgroup_array['id'] ?>','<?php echo $divname ?>','<?php echo $expectedtag ?>')">[move
                            to group]</a>
                    </font></td>
            </tr>
            <?php
        }
        ?>
    </table>
</form>
</html>
