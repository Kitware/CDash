<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: dynamic_analysis_handler.php 2796 2010-11-23 16:05:19Z zach.mullen $
  Language:  PHP
  Date:      $Date: 2010-11-23 11:05:19 -0500 (Tue, 23 Nov 2010) $
  Version:   $Revision: 2796 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
require_once('cdash/config.php');
require_once('xml_handlers/abstract_handler.php');
require_once('models/build.php');
require_once('models/uploadfile.php');
require_once('models/site.php');

/**
* 
* For each uploaded file the following steps occur:
*  1) Temporary file 'tmpXXX-base64' is created (See startElement())
*  2) Chunk of base64 data are written to that file  (See text())
*  3) File 'tmpXXX-base64' is then decoded into 'tmpXXX'
*  4) SHA1 of file 'tmpXXX' is computed
*  5) If directory '<CDASH_UPLOAD_DIRECTORY>/<SHA1>' doesn't exist, it's created
*  6) If file '<CDASH_UPLOAD_DIRECTORY>/<SHA1>/<SHA1>' doesn't exist, 'tmpXXX' is renamed accordingly
*  7) Symbolic link <CDASH_UPLOAD_DIRECTORY>/<SHA1>/<FILENAME> pointing to <CDASH_UPLOAD_DIRECTORY>/<SHA1>/<SHA1> is then created
*
* Ideally, CDash and CTest should first exchange information to make sure the file hasn't been uploaded already.
*
* As a first step, CTest could provide the SHA1 so that extra processing are avoided.
*
*/
class UploadHandler extends AbstractHandler
{
  private $BuildId;
  private $UploadFile;
  private $TmpFilename;
  private $Base64TmpFileWriteHandle;
  private $Base64TmpFilename;
  
  /** If True, means an error happened while processing the file */
  private $UploadError;

  /** Constructor */
  public function __construct($projectID)
    {
    parent::__construct($projectID);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->TmpFilename = '';
    $this->Base64TmpFileWriteHandle = 0;
    $this->Base64TmpFilename = '';
    $this->UploadError = false;
    }

  /** Start element */
  public function startElement($parser, $name, $attributes)
    {
    parent::startElement($parser, $name, $attributes);
    
    if ($this->UploadError)
      {
      return;
      }

    if($name=='SITE')
      {
      $this->Site->Name = $attributes['NAME'];
      $this->Site->Insert();

      $siteInformation = new SiteInformation();
      $buildInformation = new BuildInformation();

      // Fill in the attribute
      foreach($attributes as $key=>$value)
        {
        $siteInformation->SetValue($key,$value);
        $buildInformation->SetValue($key,$value);
        }

      $this->Site->SetInformation($siteInformation);

      $this->Build->SiteId = $this->Site->Id;
      $this->Build->Name = $attributes['BUILDNAME'];
      $this->Build->SetStamp($attributes['BUILDSTAMP']);
      $this->Build->Generator = $attributes['GENERATOR'];
      $this->Build->Information = $buildInformation;
      }
    else if($name=='UPLOAD')
      {
      $this->Build->ProjectId = $this->projectid;
      $buildid = $this->Build->GetIdFromName($this->SubProjectName);

      // If the build doesn't exist we add it
      if($buildid==0)
        {
        $this->Build->ProjectId = $this->projectid;
        $this->Build->StartTime = $start_time;
        $this->Build->EndTime = $start_time;
        $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        $this->Build->SetSubProject($this->SubProjectName);
        $this->Build->Append = $this->Append;
        $this->Build->InsertErrors = false;
        add_build($this->Build, isset($_GET['clientscheduleid']) ? $_GET['clientscheduleid'] : 0);

        $this->UpdateEndTime = true;
        $buildid = $this->Build->Id;
        }
      else
        {
        $this->Build->Id = $buildid;
        }

      $GLOBALS['PHP_ERROR_BUILD_ID'] = $buildid;
      $this->BuildId = $buildid;
      }
    else if($name == 'FILE')
      {
      $this->UploadFile = new UploadFile();
      $this->UploadFile->Filename = $attributes['FILENAME'];
      }
    else if($name == 'CONTENT')
      {
      $fileEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : 'base64';
      
      if (strcmp($fileEncoding, 'base64') != 0)
        {
        // Only base64 encoding is supported for file upload
        add_log("upload_handler:  Only 'base64' encoding is supported", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
        $this->UploadError = true;
        return;
        }
      
      // Create tmp file
      $this->TmpFilename = tempnam($GLOBALS[CDASH_UPLOAD_DIRECTORY], 'tmp'); // TODO Handle error
      if (empty($this->TmpFilename))
        {
        add_log("Failed to create temporary filename", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
        $this->UploadError = true;
        return;
        }
      $this->Base64TmpFilename = $this->TmpFilename . '-base64';
      
      // Open base64 temporary file for writting
      $this->Base64TmpFileWriteHandle = fopen($this->Base64TmpFilename, 'w');
      if (!$this->Base64TmpFileWriteHandle)
        {
        add_log("Failed to open file '".$this->Base64TmpFilename."' for writting", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
        $this->UploadError = true;
        return;
        }
      }
    } // end start element


  /** Function endElement */
  public function endElement($parser, $name)
    {
    $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);
    
    if ($this->UploadError)
      {
      return;
      }

    if($name == 'FILE' && $parent == 'UPLOAD')
      {
      $this->UploadFile->BuildId = $this->BuildId;
      
      // Close base64 temporary file writting handler
      fclose($this->Base64TmpFileWriteHandle);
      
      // Decode file using 'read by chunk' approach to minimize memory footprint
      // Note: Using stream_filter_append/stream_copy_to_stream is more efficient but 
      // return an "invalid byte sequence" on windows
      $rhandle = fopen($this->Base64TmpFilename, 'r');
      $whandle = fopen($this->TmpFilename, 'w+');
      $chunksize = 4096;
      while (!feof($rhandle))
        {
        fwrite($whandle, base64_decode(fread($rhandle, $chunksize)));
        }
      fclose($rhandle);
      fclose($whandle);
      
      // Delete base64 encoded file
      $success = unlink($this->Base64TmpFilename);
      if (!$success)
        {
        add_log("Failed to delete file '".$this->Base64TmpFilename."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_WARNING);
        }

      // Computer SHA1 of decoded file
      $upload_file_sha1 = sha1_file($this->TmpFilename);
      $upload_file_size = filesize($this->TmpFilename);
      
      $this->UploadFile->Sha1Sum = $upload_file_sha1;
      $this->UploadFile->Filesize = $upload_file_size;
      
      $upload_dir = realpath($GLOBALS[CDASH_UPLOAD_DIRECTORY]).'/'.$this->UploadFile->Sha1Sum;
      
      $uploadfilepath = $upload_dir.'/'.$this->UploadFile->Sha1Sum;

      // Check if upload directory should be created
      if (!file_exists($GLOBALS[CDASH_UPLOAD_DIRECTORY].'/'.$upload_file_sha1))
        {
        $success = mkdir($upload_dir);
        if (!$success)
          {
          add_log("Failed to create directory '".$upload_dir."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
          $this->UploadError = true;
          return;
          }
        }
      
      // Check if file has already been referenced
      if (!file_exists($GLOBALS[CDASH_UPLOAD_DIRECTORY].'/'.$upload_file_sha1.'/'.$upload_file_sha1))
        {
        $success = rename($this->TmpFilename, $uploadfilepath);
        if (!$success)
          {
          add_log("Failed to rename file '".$this->TmpFilename."' into '".$uploadfilepath."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
          $this->UploadError = true;
          return;
          }
        }
      else
        {
        // Delete decoded temporary file since it has already been addressed
        $success = unlink($this->TmpFilename);
        if (!$success)
          {
          add_log("Failed to delete file '".$this->TmpFilename."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_WARNING);
          }
        }
      
      // Generate symlink name
      $path_parts = pathinfo($this->UploadFile->Filename);
      $symlinkName = $path_parts['basename'];
      
      // Check if symlink should be created
      $createSymlink = !file_exists($upload_dir.'/'.$symlinkName);
      
      if ($createSymlink)
        {
        // Create symlink
        $success = symlink($uploadfilepath, $upload_dir.'/'.$symlinkName);
        if (!$success)
          {
          add_log("Failed to create symlink [target:'".$uploadfilepath."', name: '".$upload_dir.'/'.$symlinkName."']", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
          $this->UploadError = true;
          return;
          }
        }
      
      // Update model
      $success = $this->UploadFile->Insert();
      if (!$success)
        {
        add_log("UploadFile model - Failed to insert row associated with file: '".$this->UploadFile->Filename."'", __FILE__.':'.__LINE__.' - '.__FUNCTION__, LOG_ERR);
        }
      
      // Reset UploadError so that the handler could attempt to process following files
      $this->UploadError = false;
      }
    } // end endElement


  /** Function Text */
  public function text($parser, $data)
    {
    if ($this->UploadError)
      {
      return;
      }
      
    $parent = $this->getParent();
    $element = $this->getElement();

    if($parent == 'FILE')
      {
      switch($element)
        {
        case 'CONTENT':
          //add_log("Chunk size:" . strlen($data));
          //add_log("Chunk:" . $data);
          // Write base64 encoded chunch to temporary file
          $charsToReplace = array("\r\n", "\n", "\r");
          fwrite($this->Base64TmpFileWriteHandle, str_replace($charsToReplace, '', $data));
          break;
        }
      }
    } // end text function
}
?>
