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

use CDash\Database;
use PDO;

/** Label */
class Label
{
    public $Id;
    public $Text;

    public $BuildId;
    public $BuildFailureId;
    public $CoverageFileId;
    public $CoverageFileBuildId;
    public $DynamicAnalysisId;
    public $TestId;
    public $TestBuildId;
    public $UpdateFileKey;

    private $PDO;


    public function __construct()
    {
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function SetText($text)
    {
        $this->Text = $text;
    }

    /** Get the text of a label */
    public function GetText()
    {
        return pdo_get_field_value('SELECT text FROM label WHERE id=' . qnum($this->Id), 'text', 0);
    }

    /** Get the id from a label */
    public function GetIdFromText()
    {
        return pdo_get_field_value("SELECT id FROM label WHERE text='" . $this->Text . "'", 'id', 0);
    }

    public function GetTextFromBuildFailure($fetchType = PDO::FETCH_ASSOC)
    {
        if (!$this->BuildFailureId) {
            add_log('BuildFailureId not set', 'Label::GetTestFromBuildFailure', LOG_WARNING);
            return;
        }

        $sql = '
            SELECT text FROM label, label2buildfailure
            WHERE label.id=label2buildfailure.labelid AND
            label2buildfailure.buildfailureid=?
            ORDER BY text ASC
        ';

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->BuildFailureId]);

        return $query->fetchAll($fetchType);
    }

    public function InsertAssociation($table, $field1, $value1 = null, $field2 = null, $value2 = null)
    {
        $duplicate_sql = '';
        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE !== 'pgsql') {
            $duplicate_sql = 'ON DUPLICATE KEY UPDATE labelid=labelid';
        }
        if (!empty($value1)) {
            if (!empty($value2)) {
                $v = pdo_get_field_value(
                    "SELECT $field1 FROM $table WHERE labelid='$this->Id' " .
                    "AND $field1='$value1' AND $field2='$value2'", "$field1", 0);

                // Only do the INSERT if it's not already there:
                if (0 == $v) {
                    $query = "INSERT INTO $table (labelid, $field1, $field2)
                        VALUES ('$this->Id', '$value1', '$value2')
                        $duplicate_sql";

                    if (!pdo_query($query)) {
                        add_last_sql_error('Label::InsertAssociation');
                    }
                }
            } else {
                $v = pdo_get_field_value(
                    "SELECT $field1 FROM $table WHERE labelid='$this->Id' " .
                    "AND $field1='$value1'", "$field1", 0);

                // Only do the INSERT if it's not already there:
                if (0 == $v) {
                    $query = "INSERT INTO $table (labelid, $field1)
                        VALUES ('$this->Id', '$value1')
                        $duplicate_sql";

                    if (!pdo_query($query)) {
                        add_last_sql_error('Label::InsertAssociation');
                    }
                }
            }
        }
    }

    // Save in the database
    public function Insert()
    {
        $text = pdo_real_escape_string($this->Text);

        // Get this->Id from the database if text is already in the label table:
        $this->Id = pdo_get_field_value(
            "SELECT id FROM label WHERE text='$text'", 'id', 0);

        // Or, if necessary, insert a new row, then get the id of the inserted row:
        if ($this->Id === 0) {
            $query = "INSERT INTO label (text) VALUES ('$text')";
            if (!pdo_query($query)) {
                // This insert might have failed due to a race condition
                // during parallel processing.
                // Query again to see if it exists before throwing an error.
                $this->Id = pdo_get_field_value(
                    "SELECT id FROM label WHERE text='$text'", 'id', 0);
                if ($this->Id === 0) {
                    add_last_sql_error('Label::Insert');
                    return false;
                }
            } else {
                $this->Id = pdo_insert_id('label');
            }
        }

        // Insert relationship records, too, but only for those relationships
        // established by callers. (If coming from test.php, for example, TestId
        // will be set, but none of the others will. Similarly for other callers.)
        $this->InsertAssociation('label2build', 'buildid',
            $this->BuildId);

        $this->InsertAssociation('label2buildfailure',
            'buildfailureid', $this->BuildFailureId);

        $this->InsertAssociation('label2coveragefile',
            'buildid', $this->CoverageFileBuildId,
            'coveragefileid', $this->CoverageFileId);

        $this->InsertAssociation('label2dynamicanalysis',
            'dynamicanalysisid', $this->DynamicAnalysisId);

        $this->InsertAssociation('label2test',
            'buildid', $this->TestBuildId,
            'testid', $this->TestId);

        // TODO: Implement this:
        //
        //$this->InsertAssociation($this->UpdateFileKey,
        //  'label2updatefile', 'updatefilekey');
        return true;
    }
}
