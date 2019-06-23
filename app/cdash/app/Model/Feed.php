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
namespace CDash\Model;

class Feed
{
    public $Id;
    public $ProjectId;
    public $Date;
    public $BuildId;
    public $Type;
    public $Description;

    const TypeUnknown = 0;
    const TypeUpdate = 1;
    const TypeBuildError = 2;
    const TypeBuildWarning = 3;
    const TypeTestPassing = 4;
    const TypeTestFailing = 5;
    const TypeTestNotRun = 6;

    public function __construct()
    {
        $this->Type = Feed::TypeUnknown;
        $this->Description = '';
    }

    /** Helper function to insert a test */
    public function InsertTest($projectid, $buildid)
    {
        $build = new Build();
        $build->FillFromId($buildid);
        if ($build->GetPreviousBuildId() == 0) {
            // if we don't have a previous build then we need to count ourselves

            $query = pdo_query('SELECT name FROM build WHERE id=' . $buildid);
            if (!$query) {
                add_last_sql_error('Feed::InsertTest');
                return false;
            }
            $query_array = pdo_fetch_array($query);
            $buildname = $query_array['name'];

            $positives = pdo_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='failed'");
            $positives_array = pdo_fetch_array($positives);
            $npositives = $positives_array[0];
            if ($npositives > 0) {
                $description = $npositives . ' test';
                if ($npositives > 1) {
                    $description .= 's';
                }
                $description .= ' failed on ' . $buildname;
                $this->Insert($projectid, $buildid, Feed::TypeTestFailing, $description);
            }

            $positives = pdo_query("SELECT count(*) FROM build2test WHERE buildid='$buildid' AND status='notrun'");
            $positives_array = pdo_fetch_array($positives);
            $npositives = $positives_array[0];
            if ($npositives > 0) {
                $description = $npositives . ' test';
                if ($npositives > 1) {
                    $description .= 's';
                }
                $description .= ' not run on ' . $buildname;
                $this->Insert($projectid, $buildid, Feed::TypeTestNotRun, $description);
            }
            return;
        }

        // If we do have a previous build then we use the builderrordiff table
        // Check if we have any fixes or errors
        $query = pdo_query("SELECT * FROM testdiff AS td JOIN build AS b ON (td.buildid=b.id) WHERE td.buildid='$buildid'");
        if (!$query) {
            add_last_sql_error('Feed::InsertTest');
            return false;
        }

        while ($query_array = pdo_fetch_array($query)) {
            $type = 'not run';
            $feedtype = Feed::TypeTestNotRun;
            if ($query_array['type'] == 1) {
                // failed

                $type = 'failed';
                $feedtype = Feed::TypeTestFailing;
            } elseif ($query_array['type'] == 2) {
                // pass

                $type = 'passing';
                $feedtype = Feed::TypeTestPassing;
            }

            if (($query_array['type'] == 0 || $query_array['type'] == 1)
                && $query_array['difference_positive'] > 0
            ) {
                $description .= $query_array['difference_positive'] . ' test';

                if ($query_array['difference_positive'] > 1) {
                    $description .= 's';
                }
                $description .= ' ' . $type;
                $description .= ' introduced on ' . $query_array['name'];
                $this->Insert($projectid, $buildid, $feedtype, $description);
            }
            if ($query_array['type'] == 1 && $query_array['difference_negative'] > 0) {
                $description .= $query_array['difference_negative'] . ' test';

                if ($query_array['difference_negative'] > 1) {
                    $description .= 's';
                }
                $description .= ' fixed on ' . $query_array['name'];

                $this->Insert($projectid, $buildid, $feedtype, $description);
            }
            if ($query_array['type'] == 2 && $query_array['difference_positive'] > 0) {
                $description .= $query_array['difference_positive'] . ' new test';

                if ($query_array['difference_positive'] > 1) {
                    $description .= 's';
                }
                $description .= ' ' . $type;
                $description .= ' introduced on ' . $query_array['name'];
                $this->Insert($projectid, $buildid, $feedtype, $description);
            }
        }
    }

    /** Helper function to insert the build */
    public function InsertBuild($projectid, $buildid)
    {
        $build = new Build();
        $build->FillFromId($buildid);
        if ($build->GetPreviousBuildId() == 0) {
            // if we don't have a previous build then we need to count ourselves

            $query = pdo_query('SELECT name FROM build WHERE id=' . $buildid);
            if (!$query) {
                add_last_sql_error('Feed::InsertBuild');
                return false;
            }
            $query_array = pdo_fetch_array($query);
            $buildname = $query_array['name'];

            $positives = pdo_query('SELECT count(*) FROM builderror WHERE buildid=' . $buildid . ' AND type=0');
            $positives_array = pdo_fetch_array($positives);
            $npositives = $positives_array[0];
            $positives = pdo_query(
                'SELECT count(*) FROM buildfailure AS bf
         LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
         WHERE bf.buildid=' . $buildid . ' AND bfd.type=0');
            $positives_array = pdo_fetch_array($positives);
            $npositives += $positives_array[0];

            if ($npositives > 0) {
                $description = $npositives . ' error';
                if ($npositives > 1) {
                    $description .= 's';
                }
                $description .= ' introduced on ' . $buildname;
                $this->Insert($projectid, $buildid, Feed::TypeBuildError, $description);
            }

            $positives = pdo_query('SELECT count(*) FROM builderror WHERE buildid=' . $buildid . ' AND type=1');
            $positives_array = pdo_fetch_array($positives);
            $npositives = $positives_array[0];
            $positives = pdo_query(
                'SELECT count(*) FROM buildfailure AS bf
         LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
         WHERE bf.buildid=' . $buildid . ' AND bfd.type=1');
            $positives_array = pdo_fetch_array($positives);
            $npositives += $positives_array[0];

            if ($npositives > 0) {
                $description = $npositives . ' warning';
                if ($npositives > 1) {
                    $description .= 's';
                }
                $description .= ' introduced on ' . $buildname;
                $this->Insert($projectid, $buildid, Feed::TypeBuildWarning, $description);
            }
            return;
        }

        // If we do have a previous build then we use the builderrordiff table
        // Check if we have any fixes or errors
        $query = pdo_query("SELECT * FROM builderrordiff AS bd JOIN build AS b ON (bd.buildid=b.id) WHERE bd.buildid='$buildid'");
        if (!$query) {
            add_last_sql_error('Feed::InsertBuild');
            return false;
        }

        while ($query_array = pdo_fetch_array($query)) {
            $type = 'error';
            $feedtype = Feed::TypeBuildError;
            if ($query_array['type'] == 1) {
                // warning

                $type = 'warning';
                $feedtype = Feed::TypeBuildWarning;
            }

            if ($query_array['difference_positive'] > 0) {
                $description .= $query_array['difference_positive'] . ' ' . $type;

                if ($query_array['difference_positive'] > 1) {
                    $description .= 's';
                }
                $description .= ' introduced on ' . $query_array['name'];
                $this->Insert($projectid, $buildid, $feedtype, $description);
            } elseif ($query_array['difference_negative'] > 0) {
                $description .= $query_array['difference_negative'] . ' ' . $type;

                if ($query_array['difference_negative'] > 1) {
                    $description .= 's';
                }
                $description .= ' fixed on ' . $query_array['name'];

                $this->Insert($projectid, $buildid, $feedtype, $description);
            }
        }
    }

    /** Helper function to inser the update */
    public function InsertUpdate($projectid, $buildid)
    {
        // Check if we don't have more than one buildupdate with the same revision
        $query = pdo_query("
            SELECT count(bu.id) AS c FROM buildupdate AS bu, build2update AS b2u, build AS b WHERE b.id=b2u.buildid
            AND b.projectid='$projectid' AND bu.id=b2u.updateid AND bu.revision =
            (SELECT bu1.revision FROM buildupdate AS bu1, build2update AS b2u1 WHERE b2u1.updateid=bu1.id AND b2u1.buildid='$buildid')");
        if (!$query) {
            add_last_sql_error('Feed::InsertUpdate');
            return false;
        }
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] > 1) {
            // if we have more than one we return
            return;
        }

        // Find the date
        $query = pdo_query("SELECT bu.starttime FROM buildupdate AS bu, build2update AS b2u
                        WHERE bu.id=b2u.updateid AND b2u.buildid='$buildid'");
        $query_array = pdo_fetch_array($query);
        $starttime = $query_array['starttime'];

        // Find the number of files and the authors
        $nfiles = 0;
        $authors = array();
        $query = pdo_query("SELECT filename,author FROM updatefile AS f, buildupdate AS bu, build2update AS b2u
                        WHERE bu.id=b2u.updateid AND f.updateid=bu.id AND b2u.buildid='$buildid'");
        while ($query_array = pdo_fetch_array($query)) {
            $nfiles++;
            if (!in_array($query_array['author'], $authors)) {
                $authors[] = $query_array['author'];
            }
        }

        if ($nfiles == 0) {
            // no updates we return;
            return;
        }

        $description = $nfiles . ' file';
        if ($nfiles > 1) {
            $description .= 's';
        }
        $description .= ' changed by ';
        $n = 0;
        foreach ($authors as $author) {
            if ($n > 1) {
                break;
            }
            if ($n > 0) {
                $description .= ', ';
            }
            $description .= $author;
            $n++;
        }

        if (count($authors) > 2) {
            $c = count($authors) - 2;
            $description .= ' and ' . $c . ' others';
        }

        $description = pdo_real_escape_string($description);
        $this->Insert($projectid, $buildid, Feed::TypeUpdate, $description);
    }

    /** Delete the old feeds */
    public function DeleteOld($projectid, $days)
    {
        $secondsinday = 86400; // == 3600*24;
        $olddate_utc = gmdate(FMT_DATETIMESTD, time() - $days * $secondsinday);
        pdo_delete_query("DELETE FROM feed WHERE projectid='$projectid' AND date<'$olddate_utc'");
    }

    /** Insert a new feed */
    public function Insert($projectid, $buildid, $type, $description = '')
    {
        $this->ProjectId = $projectid;
        $this->BuildId = $buildid;
        $this->Type = $type;
        $this->Description = $description;
        $this->Date = gmdate(FMT_DATETIME);

        if (pdo_query("INSERT INTO feed (projectid,buildid,type,date,description)
                  VALUES ('$this->ProjectId','$this->BuildId','$this->Type','$this->Date','$this->Description')")) {
            $this->Id = pdo_insert_id('feed');
        } else {
            add_last_sql_error('Feed Insert');
            return false;
        }

        // Delete the old feed (30 days)
        $this->DeleteOld($projectid, 30);
    }

    // Get the name of the size
    public function GetFeed($projectid, $limit)
    {
        if (!$projectid) {
            echo "Feed::GetFeed(): $projectid not set";
            return false;
        }

        $query = pdo_query('SELECT * FROM feed WHERE projectid=' . qnum($projectid) . ' ORDER BY id DESC LIMIT ' . $limit);
        if (!$query) {
            add_last_sql_error('Feed::GetFeed');
            return false;
        }

        $feeds = array();
        while ($feed_array = pdo_fetch_array($query)) {
            $feeds[] = $feed_array;
        }
        return $feeds;
    }
}
