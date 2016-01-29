<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE.  See the above copyright notices for more information.

  =========================================================================*/

/** Label */
class label
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

    public function SetText($text)
    {
        $this->Text = $text;
    }

    /** Get the text of a label */
    public function GetText()
    {
        return pdo_get_field_value("SELECT text FROM label WHERE id=".qnum($this->Id), "text", 0);
    }

    /** Get the id from a label */
    public function GetIdFromText()
    {
        return pdo_get_field_value("SELECT id FROM label WHERE text='".$this->Text."'", "id", 0);
    }


    public function InsertAssociation($table, $field1, $value1=null, $field2=null, $value2=null)
    {
        if (!empty($value1)) {
            if (!empty($value2)) {
                $v = pdo_get_field_value(
                        "SELECT $field1 FROM $table WHERE labelid='$this->Id' ".
                        "AND $field1='$value1' AND $field2='$value2'", "$field1", 0);

                // Only do the INSERT if it's not already there:
                if (0 == $v) {
                    $query = "INSERT INTO $table (labelid, $field1, $field2) ".
                        "VALUES ('$this->Id', '$value1', '$value2')";

                    if (!pdo_query($query)) {
                        add_last_sql_error("Label::InsertAssociation");
                    }
                }
            } else {
                $v = pdo_get_field_value(
                        "SELECT $field1 FROM $table WHERE labelid='$this->Id' ".
                        "AND $field1='$value1'", "$field1", 0);

                // Only do the INSERT if it's not already there:
                if (0 == $v) {
                    $query = "INSERT INTO $table (labelid, $field1) ".
                        "VALUES ('$this->Id', '$value1')";

                    if (!pdo_query($query)) {
                        add_last_sql_error("Label::InsertAssociation");
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
        if (0 == $this->Id) {
            $query = "INSERT INTO label (text) VALUES ('$text')";
            if (!pdo_query($query)) {
                add_last_sql_error('Label::Insert');
                return false;
            }

            $this->Id = pdo_insert_id('label');
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
