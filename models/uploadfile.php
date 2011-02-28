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
  var $Content;
  var $MD5Sum;
  var $BuildId;
  var $FileEncoding;
  var $FileCompression;

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

    if(!$this->Content)
      {
      echo "UploadFile::Insert(): Content is not set<br>";
      return false;
      }

    if(strtolower($this->FileEncoding) != 'base64')
      {
      echo "UploadFile::Insert(): Unsupported file encoding<br>";
      return false;
      }
    $filename = pdo_real_escape_string(basename($this->Filename));
    $filedata = base64_decode(trim($this->Content));
    if($this->FileEncoding == 'gzip')
      {
      @$filedata = gzuncompress($filedata);
      }

    $md5sum = md5($filedata);
    $filesize = strlen($filedata);
    $filedata = pdo_real_escape_string($filedata);

    //check if the file already exists
    $filequery = pdo_query("SELECT id FROM uploadfile WHERE md5sum = '$md5sum' AND filename ='$filename'");
    if(pdo_num_rows($filequery) == 0)
      {
      // Insert the file into the database
      $query = "INSERT INTO uploadfile (file, filename, filesize, md5sum) VALUES ('$filedata','$filename','$filesize','$md5sum')";
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
}
?>
