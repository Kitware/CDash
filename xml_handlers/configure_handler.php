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
require_once('models/label.php');
require_once('models/site.php');
require_once('models/buildconfigure.php');

class ConfigureHandler extends AbstractHandler
{  
  private $StartTimeStamp;
  private $EndTimeStamp;

  private $Configure;
  private $Label;

  public function __construct($projectid)
    {
    parent::__construct($projectid);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->Configure = new BuildConfigure();
    }

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
    else if($name == 'LABEL')
      {
      $this->Label = new Label();
      }
    }

  public function endElement($parser, $name)
    {
    parent::endElement($parser, $name);

    if($name=='CONFIGURE')
      { 
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
        $this->Build->SetSubProject($this->SubProjectName);
        add_build($this->Build);
        $buildid = $this->Build->Id;
        }
      $this->Configure->BuildId = $buildid;
      $this->Configure->StartTime = $start_time;
      $this->Configure->EndTime = $end_time;
      
      // Insert the configure
      if($this->Configure->Exists())
        {
        $this->Configure->Delete();
        }
      $this->Configure->Insert();
      // Insert errors from the log file 
      $this->Configure->ComputeErrors();
      
      $this->Build->Id = $buildid;
      $this->Build->ComputeConfigureDifferences();
      }
    else if($name == 'LABEL')
      {
      if(isset($this->Configure))
        {
        $this->Configure->AddLabel($this->Label);
        }
      }
    }

  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();

    if($parent=='CONFIGURE')
      {
      switch ($element)
        {
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
          break;
        case 'STARTCONFIGURETIME':
          $this->StartTimeStamp = $data;
          break;
        case 'ELAPSEDMINUTES':
          $this->EndTimeStamp = $this->StartTimeStamp+$data*60;
          break;
        case 'BUILDCOMMAND':
          $this->Configure->Command .= $data;
          break;
        case 'LOG':
          $this->Configure->Log .= $data;
          break;
        case 'CONFIGURECOMMAND':
          $this->Configure->Command .= $data;
          break;
        case 'CONFIGURESTATUS':
          $this->Configure->Status .= $data;
          break;
        }
      }

    if($element == 'LABEL')
      {
      $this->Label->SetText($data);
      }
    }
}
?>
