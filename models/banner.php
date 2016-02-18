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

// It is assumed that appropriate headers should be included before including this file

class banner
{
    private $ProjectId;
    private $Text;

    public function __construct()
    {
        $this->ProjectId = -1;
    }

  /** Return the text */
  public function GetText()
  {
      $query = pdo_query("SELECT text FROM banner WHERE projectid=".qnum($this->ProjectId));
      if (pdo_num_rows($query) == 0) {
          return false;
      }
      $query_array = pdo_fetch_array($query);
      $this->Text = $query_array['text'];
      if (strlen($this->Text)==0) {
          return false;
      }
      return $this->Text;
  }

  /** Set the project id */
  public function SetProjectId($projectid)
  {
      $this->ProjectId = $projectid;
  }

  /** Return if exists */
  public function Exists()
  {
      $query = pdo_query("SELECT count(*) AS c FROM banner WHERE projectid=".qnum($this->ProjectId));
      $query_array = pdo_fetch_array($query);
      if ($query_array['c']>0) {
          return true;
      }
      return false;
  }

  // Save the banner in the database
  public function SetText($text)
  {
      if ($this->ProjectId==-1) {
          echo "Banner::SetText(): no ProjectId specified";
          return false;
      }

      $this->Text = pdo_real_escape_string($text);

    // Check if the project is already
    if ($this->Exists()) {
        // Update the project
      $query = "UPDATE banner SET";
        $query .= " text='".$this->Text."'";
        $query .= " WHERE projectid='".$this->ProjectId."'";
        if (!pdo_query($query)) {
            add_last_sql_error("Banner:SetText", $this->ProjectId);
            echo $query;
            return false;
        }
    } else {
        // insert

      $query = "INSERT INTO banner (projectid,text)
                VALUES (".qnum($this->ProjectId).",'".$this->Text."')";
        if (!pdo_query($query)) {
            add_last_sql_error("Banner:SetText", $this->ProjectId);
            echo $query;
            return false;
        }
    }
      return true;
  }  // end SetText
} // end class Banner;
