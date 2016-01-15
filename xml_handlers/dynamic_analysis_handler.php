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

    private $DynamicAnalysis;
    private $DynamicAnalysisDefect;
    private $Label;

  /** Constructor */
  public function __construct($projectID, $scheduleID)
  {
      parent::__construct($projectID, $scheduleID);
      $this->Build = new Build();
      $this->Site = new Site();
      $this->UpdateEndTime = false;
  }


  /** Start element */
  public function startElement($parser, $name, $attributes)
  {
      parent::startElement($parser, $name, $attributes);

      if ($name=='SITE') {
          $this->Site->Name = $attributes['NAME'];
          if (empty($this->Site->Name)) {
              $this->Site->Name = "(empty)";
          }
          $this->Site->Insert();

          $siteInformation = new SiteInformation();
          $buildInformation =  new BuildInformation();

      // Fill in the attribute
      foreach ($attributes as $key=>$value) {
          $siteInformation->SetValue($key, $value);
          $buildInformation->SetValue($key, $value);
      }

          $this->Site->SetInformation($siteInformation);

          $this->Build->SiteId = $this->Site->Id;
          $this->Build->Name = $attributes['BUILDNAME'];
          if (empty($this->Build->Name)) {
              $this->Build->Name = "(empty)";
          }
          $this->Build->SetStamp($attributes['BUILDSTAMP']);
          $this->Build->Generator = $attributes['GENERATOR'];
          $this->Build->Information = $buildInformation;
      } elseif ($name=='DYNAMICANALYSIS') {
          $this->Checker = $attributes['CHECKER'];
      } elseif ($name=='TEST' && isset($attributes['STATUS'])) {
          $this->DynamicAnalysis = new DynamicAnalysis();
          $this->DynamicAnalysis->Checker = $this->Checker;
          $this->DynamicAnalysis->Status = $attributes['STATUS'];
      } elseif ($name=='DEFECT') {
          $this->DynamicAnalysisDefect = new DynamicAnalysisDefect();
          $this->DynamicAnalysisDefect->Type = $attributes['TYPE'];
      } elseif ($name == 'LABEL') {
          $this->Label = new Label();
      } elseif ($name == 'LOG') {
          $this->DynamicAnalysis->LogCompression = isset($attributes['COMPRESSION']) ? $attributes['COMPRESSION'] : '';
          $this->DynamicAnalysis->LogEncoding = isset($attributes['ENCODING']) ? $attributes['ENCODING'] : '';
      }
  } // end start element


  /** Function endElement */
  public function endElement($parser, $name)
  {
      $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

      if ($name == "STARTTESTTIME" && $parent == 'DYNAMICANALYSIS') {
          $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
          $this->Build->ProjectId = $this->projectid;
          $this->Build->GetIdFromName($this->SubProjectName);
          $this->Build->RemoveIfDone();

      // If the build doesn't exist we add it
      if ($this->Build->Id == 0) {
          $this->Build->ProjectId = $this->projectid;
          $this->Build->StartTime = $start_time;
          $this->Build->EndTime = $start_time;
          $this->Build->SubmitTime = gmdate(FMT_DATETIME);
          $this->Build->SetSubProject($this->SubProjectName);
          $this->Build->InsertErrors = false;
          add_build($this->Build, $this->scheduleid);
          $this->UpdateEndTime = true;
      } else {
          // Remove all the previous analysis
        $this->DynamicAnalysis = new DynamicAnalysis();
          $this->DynamicAnalysis->BuildId = $this->Build->Id;
          $this->DynamicAnalysis->RemoveAll();
          unset($this->DynamicAnalysis);
      }
          $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
      } elseif ($name == "TEST" && $parent == 'DYNAMICANALYSIS') {
          $this->DynamicAnalysis->BuildId = $this->Build->Id;
          $this->DynamicAnalysis->Insert();
      } elseif ($name=='DEFECT') {
          $this->DynamicAnalysis->AddDefect($this->DynamicAnalysisDefect);
          unset($this->DynamicAnalysisDefect);
      } elseif ($name == "SITE") {
          if ($this->UpdateEndTime) {
              $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
        $this->Build->UpdateEndTime($end_time);
          }
      } elseif ($name == 'LABEL') {
          if (isset($this->DynamicAnalysis)) {
              $this->DynamicAnalysis->AddLabel($this->Label);
          }
      } elseif ($name == 'DYNAMICANALYSIS') {
          // If everything is perfect CTest doesn't send any <test>
      // But we still want a line showing the current dynamic analysis
      if (!isset($this->DynamicAnalysis)) {
          $this->DynamicAnalysis = new DynamicAnalysis();
          $this->DynamicAnalysis->BuildId = $this->Build->Id;
          $this->DynamicAnalysis->Status='passed';
          $this->DynamicAnalysis->Checker = $this->Checker;
          $this->DynamicAnalysis->Insert();
      }
      }
  } // end endElement


  /** Function Text */
  public function text($parser, $data)
  {
      $parent = $this->getParent();
      $element = $this->getElement();

      if ($parent=='DYNAMICANALYSIS') {
          switch ($element) {
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
      } elseif ($parent=='TEST') {
          switch ($element) {
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
      } elseif ($parent=='RESULTS') {
          if ($element=='DEFECT') {
              $this->DynamicAnalysisDefect->Value .= $data;
          }
      } elseif ($element == 'LABEL') {
          $this->Label->SetText($data);
      }
  } // end text function
}
