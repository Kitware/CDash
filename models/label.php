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
class Label
{
  var $Id;
  var $Text;

  var $BuildId;
  var $BuildFailureId;
  var $CoverageFileId;
  var $DynamicAnalysisId;
  var $TestId;
  var $UpdateFileKey;


  function SetText($text)
    {
    $this->Text = $text;
    }


  function InsertAssociation($value, $table, $field)
    {
    if(!empty($value))
      {
      $v = pdo_get_field_value(
        "SELECT $field FROM $table WHERE labelid='$this->Id' ".
        "AND $field='$value'", "$field", 0);

      // Only do the INSERT if it's not already there:
      //
      if (0 == $v)
        {
        $query = "INSERT INTO $table (labelid, $field) ".
          "VALUES ('$this->Id', '$value')";

        //add_log("associating labelid='$this->Id' with $field='$value'",
        //  'Label::InsertAssociation');

        if(!pdo_query($query))
          {
          add_last_sql_error("Label::InsertAssociation");
          }
        }
      //else
      //  {
      //  add_log("* labelid='$this->Id' already associated with $field=" .
      //    "'$value' v='$v'", 'Label::InsertAssociation');
      //  }
      }
    }


  // Save in the database
  function Insert()
    {
    $text = pdo_real_escape_string($this->Text);

    // Get this->Id from the database if text is already in the label table:
    //
    $this->Id = pdo_get_field_value(
      "SELECT id FROM label WHERE text='$text'", 'id', 0);

    // Or, if necessary, insert a new row, then get the id of the inserted row:
    //
    if(0 == $this->Id)
      {
      $query = "INSERT INTO label (text) VALUES ('$text')";
      if(!pdo_query($query))
        {
        add_last_sql_error('Label::Insert');
        return false;
        }

      $this->Id = pdo_insert_id('label');
      //add_log('new Label::Id='.$this->Id, 'Label::Insert');
      }
    else
      {
      //add_log('existing Label::Id='.$this->Id, 'Label::Insert');
      }


    // Insert relationship records, too, but only for those relationships
    // established by callers. (If coming from test.php, for example, TestId
    // will be set, but none of the others will. Similarly for other callers.)
    //
    $this->InsertAssociation($this->BuildId,
      'label2build', 'buildid');

    $this->InsertAssociation($this->BuildFailureId,
      'label2buildfailure', 'buildfailureid');

    $this->InsertAssociation($this->CoverageFileId,
      'label2coveragefile', 'coveragefileid');

    $this->InsertAssociation($this->DynamicAnalysisId,
      'label2dynamicanalysis', 'dynamicanalysisid');

    $this->InsertAssociation($this->TestId,
      'label2test', 'testid');

    // TODO: Implement this:
    //
    //$this->InsertAssociation($this->UpdateFileKey,
    //  'label2updatefile', 'updatefilekey');


    return true;
    }
}

?>
