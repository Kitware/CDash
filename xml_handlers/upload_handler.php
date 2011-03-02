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
require_once('xml_handlers/abstract_handler.php');
require_once('models/build.php');
require_once('models/uploadfile.php');
require_once('models/site.php');

class UploadHandler extends AbstractHandler
{
  private $BuildId;
  private $UploadFile;

  private $Content;
  private $FileCompression;
  private $FileEncoding;

  /** Constructor */
  public function __construct($projectID)
    {
    parent::__construct($projectID);
    $this->Build = new Build();
    $this->Site = new Site();
    }

  /** Start element */
  public function startElement($parser, $name, $attributes)
    {
    parent::startElement($parser, $name, $attributes);

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
      $this->FileEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : 'base64';
      $this->FileCompression = isset($attributes['COMPRESSION']) ? $attributes['COMPRESSION'] : '';
      $this->UploadFile->Content = '';
      }
    } // end start element


  /** Function endElement */
  public function endElement($parser, $name)
    {
    $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

    if($name == 'FILE' && $parent == 'UPLOAD')
      {
      $this->UploadFile->BuildId = $this->BuildId;
      $this->UploadFile->FileEncoding = $this->FileEncoding;
      $this->UploadFile->FileCompression = $this->FileCompression;
      $this->UploadFile->Insert();
      }
    } // end endElement


  /** Function Text */
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();

    if($parent == 'FILE')
      {
      switch($element)
        {
        case 'CONTENT':
          $this->UploadFile->Content .= $data;
          break;
        }
      }
    } // end text function
}
?>
