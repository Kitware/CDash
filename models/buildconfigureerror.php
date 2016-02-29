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

/** BuildConfigureError class */
class buildconfigureerror
{
    public $Type;
    public $Text;
    public $BuildId;

  /** Return if exists */
  public function Exists()
  {
      if (!$this->BuildId || !is_numeric($this->BuildId)) {
          echo "BuildConfigureError::Save(): BuildId not set";
          return false;
      }

      if (!$this->Type || !is_numeric($this->Type)) {
          echo "BuildConfigureError::Save(): Type not set";
          return false;
      }

      $query = pdo_query("SELECT count(*) AS c FROM configureerror WHERE buildid='".$this->BuildId."'
                         AND type='".$this->Type."' AND text='".$this->Text."'");
      add_last_sql_error("BuildConfigureError:Exists", 0, $this->BuildId);
      $query_array = pdo_fetch_array($query);
      if ($query_array['c']>0) {
          return true;
      }
      return false;
  }

  /** Save in the database */
  public function Save()
  {
      if (!$this->BuildId || !is_numeric($this->BuildId)) {
          echo "BuildConfigureError::Save(): BuildId not set";
          return false;
      }

      if (!$this->Type || !is_numeric($this->Type)) {
          echo "BuildConfigureError::Save(): Type not set";
          return false;
      }

      if (!$this->Exists()) {
          $text = pdo_real_escape_string($this->Text);
          $query = "INSERT INTO configureerror (buildid,type,text)
                VALUES (".qnum($this->BuildId).",".qnum($this->Type).",'$text')";
          if (!pdo_query($query)) {
              add_last_sql_error("BuildConfigureError:Save", 0, $this->BuildId);
              return false;
          }
      }
      return true;
  }
}
