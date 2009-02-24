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
require_once('models/dynamicanalysis.php');

class DynamicAnalysisHandler extends AbstractHandler
{
  private $StartTimeStamp;
  private $EndTimeStamp;
  private $Checker;
  private $UpdateEndTime;
  private $BuildId;

  private $DynamicAnalysis;
  private $DynamicAnalysisDefect;
  private $Label;


  /** Constructor */
  public function __construct($projectID)
    {
    parent::__construct($projectID);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->UpdateEndTime = false; 
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
       $buildInformation =  new BuildInformation();
      
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
    else if($name=='DYNAMICANALYSIS')
      {
      $this->Checker = $attributes['CHECKER'];    
      }
    else if($name=='TEST' && isset($attributes['STATUS']))
      {
      $this->DynamicAnalysis = new DynamicAnalysis();
      $this->DynamicAnalysis->Checker = $this->Checker;
      $this->DynamicAnalysis->Status = $attributes['STATUS'];
      }
    else if($name=='DEFECT')
      {
      $this->DynamicAnalysisDefect = new DynamicAnalysisDefect();
      $this->DynamicAnalysisDefect->Type = $attributes['TYPE'];
      }
    else if($name == 'LABEL')
      {
      $this->Label = new Label();
      }
    } // end start element


  /** Function endElement */
  public function endElement($parser, $name)
    {
    $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

    if($name == "STARTTESTTIME" && $parent == 'DYNAMICANALYSIS')
      {  
      $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
      $this->Build->ProjectId = $this->projectid;
      $buildid = $this->Build->GetIdFromName($this->SubProjectName);

      // If the build doesn't exist we add it
      if($buildid==0)
        {
        $this->Build->ProjectId = $this->projectid;
        $this->Build->StartTime = $start_time;
        $this->Build->EndTime = $start_time;
        $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        add_build($this->Build);
        $this->UpdateEndTime = true;  
        $buildid = $this->Build->Id;
        }
      else
        {
        // Remove all the previous analysis
        $this->DynamicAnalysis = new DynamicAnalysis();
        $this->DynamicAnalysis->BuildId = $buildid;
        $this->DynamicAnalysis->RemoveAll();
        unset($this->DynamicAnalysis);
        }  
      $this->BuildId = $buildid;
      }
    else if($name == "TEST" && $parent == 'DYNAMICANALYSIS')
      {  
      $this->DynamicAnalysis->BuildId = $this->BuildId;
      $this->DynamicAnalysis->Insert();
      }
    else if($name=='DEFECT')
      {
      $this->DynamicAnalysis->AddDefect($this->DynamicAnalysisDefect);
      unset($this->DynamicAnalysisDefect);
      }
    else if($name == "SITE")
      {
      if($this->UpdateEndTime)
        {
        $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
        $this->Build->UpdateEndTime($end_time);
        }
      }
    else if($name == 'LABEL')
      {
      if(isset($this->DynamicAnalysis))
        {
        $this->DynamicAnalysis->AddLabel($this->Label);
        }
      }
    } // end endElement


  /** Function Text */
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();

    if($parent=='DYNAMICANALYSIS')
      {
      switch($element)
        {
        case 'STARTBUILDTIME': 
          $this->StartTimeStamp .= $data;
          break;
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
          break;
        case 'ELAPSEDMINUTES':
          $this->EndTimeStamp = $this->StartTimeStamp+$data*60;
          break;
        }
       }
    else if($parent=='TEST')
      {  
      switch($element)
        {
        case 'NAME':
          $this->DynamicAnalysis->Name .= $data;
          break;
        case 'PATH':
          $this->DynamicAnalysis->Path .= $data;
          break;
        case 'FULLCOMMANDLINE':
          $this->DynamicAnalysis->FullCommandLine .= $data;
          break;
        case 'LOG':
          $this->DynamicAnalysis->Log .= $data;
          break;
        }
      }
    else if($parent=='RESULTS')
      {
      if($element=='DEFECT')
        {
        $this->DynamicAnalysisDefect->Value .= $data;
        }
      }
    else if($element == 'LABEL')
      {
      $this->Label->SetText($data);
      }
    } // end text function
}
?>
