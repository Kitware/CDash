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
class buildnote
{
    public $Id;
    public $Time;
    public $Text;
    public $Name;
    public $Crc32;
    public $BuildId;

  /** Get the CRC32 */
  public function GetCrc32()
  {
      if (strlen($this->Crc32)>0) {
          return $this->Crc32;
      }

    // Compute the CRC32 for the note
    $text = pdo_real_escape_string($this->Text);
      $timestamp = pdo_real_escape_string($this->Time);
      $name = pdo_real_escape_string($this->Name);

      $this->Crc32 = crc32($text.$name);
      return $this->Crc32;
  }


  // Insert in the database
  public function Insert()
  {
      if (!$this->BuildId) {
          echo "BuildNote::Insert(): BuildId is not set<br>";
          return false;
      }

      if (!$this->Time) {
          echo "BuildNote::Insert(): Time is not set<br>";
          return false;
      }

      if (!$this->Name) {
          echo "BuildNote::Insert(): Name is not set<br>";
          return false;
      }

      if (!$this->Text) {
          echo "BuildNote::Insert(): Text is not set<br>";
          return false;
      }

    // Check if the note already exists
    $crc32 = $this->GetCrc32();

      $text = pdo_real_escape_string($this->Text);
      $timestamp = pdo_real_escape_string($this->Time);
      $name = pdo_real_escape_string($this->Name);

      $notecrc32 =  pdo_query("SELECT id FROM note WHERE crc32='$crc32'");
      if (pdo_num_rows($notecrc32) == 0) {
          if ($this->Id) {
              $query = "INSERT INTO note (id,text,name,crc32) VALUES ('$this->Id','$text','$name','$crc32')";
          } else {
              $query = "INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')";
          }

          if (!pdo_query($query)) {
              add_last_sql_error("BuildNote:Insert", 0, $this->BuildId);
              return false;
          }

          if (!$this->Id) {
              $this->Id = pdo_insert_id("note");
          }
      } else {
          // already there

      $notecrc32_array = pdo_fetch_array($notecrc32);
          $this->Id = $notecrc32_array["id"];
      }

      if (!$this->Id) {
          echo "BuildNote::Insert(): No NoteId";
          return false;
      }

      $query = "INSERT INTO build2note (buildid,noteid,time)
              VALUES ('$this->BuildId','$this->Id','$this->Time')";
      if (!pdo_query($query)) {
          add_last_sql_error("BuildNote:Insert", 0, $this->BuildId);
          return false;
      }
      return true;
  }
}
