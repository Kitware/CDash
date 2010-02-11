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
require_once 'xml_handlers/abstract_handler.php';
require_once('models/build.php');
require_once('models/site.php');
require_once('models/buildupdate.php');

/** Write the updates in one block 
 *  In case of a lot of updates this might take up some memory */
class UpdateHandler extends AbstractHandler
{
  private $StartTimeStamp;
  private $EndTimeStamp;
  
  private $Update;
  private $UpdateFile;
  
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
    if($name=='UPDATE')
      {
      $this->Update = new BuildUpdate();
      } 
   else if($name=='UPDATED' || $name=='CONFLICTING' || $name=='MODIFIED') 
     {
     $this->UpdateFile = new BuildUpdateFile();
     $this->UpdateFile->Status = $name;
     }
    }
  
  /** End element */
  public function endElement($parser, $name)
    {
    parent::endElement($parser, $name);
    if($name=='SITE')
      {
      $this->Site->Insert();
      }      
    else if($name == 'UPDATE')
      {
      $this->Build->SiteId = $this->Site->Id;
      
      $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
      $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
      $submit_time = gmdate(FMT_DATETIME);
      
      $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
      $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

      $this->Build->ProjectId = $this->projectid;
      $buildid = $this->Build->GetIdFromName($this->SubProjectName);

      // If the build doesn't exist we add it
      if($buildid==0)
        {
        $this->Build->ProjectId = $this->projectid;
        $this->Build->StartTime = $start_time;
        $this->Build->EndTime = $end_time;
        $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        $this->Build->InsertErrors = false;
        add_build($this->Build); 
        $buildid = $this->Build->Id;
        }
      else
        {
        $this->Build->Id = $buildid;
        $this->Build->ProjectId = $this->projectid;
        $this->Build->StartTime = $start_time;
        $this->Build->EndTime = $end_time;
        $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        }
        
      $this->Update->BuildId = $buildid;
      $this->Update->StartTime = $start_time;
      $this->Update->EndTime = $end_time;
            
      // Insert the update
      $this->Update->Insert();
      
      //Compute the update statistics
      $this->Build->ComputeUpdateStatistics();
      }
    else if($name=='UPDATED' || $name=='CONFLICTING' || $name=='MODIFIED') 
       {
       $this->Update->AddFile($this->UpdateFile);
       unset($this->UpdateFile);
       }
    } // end function endElement
  
  /** Text */
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();
    if($parent == 'UPDATE')
      {
      switch ($element)
        {      
        case 'BUILDNAME':
          $this->Build->Name = $data;
          break;
        case 'BUILDSTAMP':
          $this->Build->SetStamp($data);
          break;
        case 'SITE':
          $this->Site->Name = $data;
          break;
        case 'STARTTIME':
          $this->StartTimeStamp = $data;
          break;
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->getBuildStamp());
          break;
        case 'ENDTIME':
          $this->EndTimeStamp = $data;
          break;
        case 'ENDDATETIME':
          $this->EndTimeStamp = str_to_time($data, $this->getBuildStamp());
          break;  
        case 'UPDATECOMMAND':
          $this->Update->Command = $data;
          break;
        case 'UPDATETYPE':
          $this->Update->Type = $data;
          break;
        case 'REVISION':
          $this->Update->Revision = $data;
          break;
        case 'PRIORREVISION':
          $this->Update->PriorRevision = $data;
          break;
        case 'PATH':
          $this->Update->Path = $data;
          break; 
        case 'UPDATERETURNSTATUS':
          $this->Update->Status = $data;
          break;
        }
      } 
          
    else if($parent != 'REVISIONS' && $element=='FULLNAME') 
      {
      $this->UpdateFile->Filename = $data;
      }
    else if($parent != 'REVISIONS' && $element=='CHECKINDATE') 
      {
      $this->UpdateFile->CheckinDate = $data;
      } 
    else if($parent != 'REVISIONS' && $element=='AUTHOR') 
      {
      $this->UpdateFile->Author = $data;
      } 
    else if($parent != 'REVISIONS' && $element=='EMAIL') 
      {
      $this->UpdateFile->Email = $data;
      } 
    else if($parent != 'REVISIONS' && $element=='LOG') 
      {
      $this->UpdateFile->Log .= $data;
      }
    else if($parent != 'REVISIONS' && $element=='REVISION') 
      {
      if($data=='Unknown')
        {
        $data = -1;
        }
      $this->UpdateFile->Revision = $data;
      } 
   else if($parent != 'REVISIONS' && $element=='PRIORREVISION') 
     {
     if($data=='Unknown')
       {
       $data = -1;
       }
     $this->UpdateFile->PriorRevision = $data;      
     }
    }
}
?>
