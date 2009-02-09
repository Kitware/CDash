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
  var $CoverageFileId;
  var $DynamicAnalysisId;
  var $TestId;
  var $UpdateFileKey;

  function SetText($text)
    {
    $this->Text = $text;
    }

  // Save in the database
  function Insert()
    {
    $text = pdo_real_escape_string($this->Text);

    // Get this->Id from the database if text is already in the label table:
    //
    $query = pdo_query("SELECT id FROM label WHERE text='$text'");
    if(pdo_num_rows($query)>0)
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      }

    // Or, if necessary, insert a new row, then get the id of the inserted row:
    //
    if(empty($this->Id))
      {
      $query = "INSERT INTO label (text) VALUES ('$text')";
      if(!pdo_query($query))
        {
        add_last_sql_error("Label::Insert");
        return false;
        }

      $this->Id = pdo_insert_id("label");
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
    if(!empty($this->BuildId))
      {
      $query = "INSERT INTO label2build (labelid, buildid) ".
        "VALUES ('$this->Id', '$this->BuildId')";
      //add_log('associating labelid='.$this->Id.' with buildid='.$this->BuildId,
      //  'Label::Insert');
      pdo_query($query);
        // intentionally do not report sql errors - "duplicate key" (already
        // exists) "errors" may validly occur frequently
      }

    if(!empty($this->CoverageFileId))
      {
      $query = "INSERT INTO label2coveragefile (labelid, coveragefileid) ".
        "VALUES ('$this->Id', '$this->CoverageFileId')";
      //add_log('associating labelid='.$this->Id.' with coveragefileid='.$this->CoverageFileId,
      //  'Label::Insert');
      pdo_query($query);
        // intentionally do not report sql errors - "duplicate key" (already
        // exists) "errors" may validly occur frequently
      }

    if(!empty($this->DynamicAnalysisId))
      {
      $query = "INSERT INTO label2dynamicanalysis (labelid, dynamicanalysisid) ".
        "VALUES ('$this->Id', '$this->DynamicAnalysisId')";
      //add_log('associating labelid='.$this->Id.' with dynamicanalysisid='.$this->DynamicAnalysisId,
      //  'Label::Insert');
      pdo_query($query);
        // intentionally do not report sql errors - "duplicate key" (already
        // exists) "errors" may validly occur frequently
      }

    if(!empty($this->TestId))
      {
      $query = "INSERT INTO label2test (labelid, testid) ".
        "VALUES ('$this->Id', '$this->TestId')";
      //add_log('associating labelid='.$this->Id.' with testid='.$this->TestId,
      //  'Label::Insert');
      pdo_query($query);
        // intentionally do not report sql errors - "duplicate key" (already
        // exists) "errors" may validly occur frequently
      }

    // TODO: Implement this:
    //
    //if(!empty($this->UpdateFileKey))
    //  {
    //  $query = "INSERT INTO label2updatefile (labelid, updatefilekey) ".
    //    "VALUES ('$this->Id', '$this->UpdateFileKey')";
    //  //add_log('associating labelid='.$this->Id.' with updatefilekey='.$this->UpdateFileKey,
    //  //  'Label::Insert');
    //  pdo_query($query);
    //    // intentionally do not report sql errors - "duplicate key" (already
    //    // exists) "errors" may validly occur frequently
    //  }

    return true;
    }
}

?>
