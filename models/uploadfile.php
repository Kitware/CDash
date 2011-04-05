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
class UploadFile
{
  var $Id;
  var $Filename;
  var $Filesize;
  var $Sha1Sum;
  var $BuildId;

  // Insert in the database
  function Insert()
    {
    if(!$this->BuildId)
      {
      echo "UploadFile::Insert(): BuildId is not set<br>";
      return false;
      }

    if(!$this->FileEncoding)
      {
      echo "UploadFile::Insert(): FileEncoding is not set<br>";
      return false;
      }

    if(!$this->Filename)
      {
      echo "UploadFile::Insert(): Filename is not set<br>";
      return false;
      }

    if(!$this->Sha1Sum)
      {
      echo "UploadFile::Insert(): Sha1Sum is not set<br>";
      }

    if(!$this->Filesize)
      {
      echo "UploadFile::Insert(): Filesize is not set<br>";
      }

    $filename = pdo_real_escape_string(basename($this->Filename));

    //check if the file already exists
    $filequery = pdo_query("SELECT id FROM uploadfile WHERE sha1sum = '".$this->Sha1Sum."' AND filename ='$filename'");
    if(pdo_num_rows($filequery) == 0)
      {
      // Insert the file into the database
      $query = "INSERT INTO uploadfile (filename, filesize, sha1sum) VALUES ('$filename','$this->Filesize','$this->Sha1Sum')";
      if(!pdo_query($query))
        {
        add_last_sql_error('Uploadfile::Insert', 0, $this->BuildId);
        return false;
        }
      $this->Id = pdo_insert_id("uploadfile");
      }
    else
      {
      $filequery_array = pdo_fetch_array($filequery);
      $this->Id = $filequery_array["id"];
      }

    if(!$this->Id)
      {
      echo "UploadFile::Insert(): No Id";
      return false;
      }

    if(!pdo_query("INSERT INTO build2uploadfile (fileid, buildid)
                   VALUES ('$this->Id','$this->BuildId')"))
      {
      add_last_sql_error("UploadFile::Insert", 0, $this->BuildId);
      return false;
      }
    return true;
    }

  function Fill()
    {
    if(!$this->Id)
      {
      echo "UploadFile::Fill(): Id not set";
      return false;
      }
    $query = pdo_query("SELECT filename, filesize, sha1sum FROM uploadfile WHERE id='$this->Id'");
    if(!$query)
      {
      add_last_sql_error('Uploadfile::Fill', 0, $this->Id);
      return false;
      }
    if(pdo_num_rows($query) > 0)
      {
      $fileArray = pdo_fetch_array($query);
      $this->Sha1Sum = $fileArray['sha1sum'];
      $this->Filename = $fileArray['filename'];
      $this->Filesize = $fileArray['filesize'];
      }
    else
      {
      echo "UploadFile::Fill(): Invalid id";
      return false;
      }
    return true;
    }
}
?>
