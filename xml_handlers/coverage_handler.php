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
require_once('models/coverage.php');

class CoverageHandler extends AbstractHandler
{  
  private $StartTimeStamp;
  private $EndTimeStamp;
  
  private $Coverage;
  private $CoverageFile;
  private $CoverageSummary;
  
  /** Constructor */
  public function __construct($projectID)
    {
    parent::__construct($projectID);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->CoverageSummary = new CoverageSummary();
    }
  
  /** startElement */
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
    else if($name=='FILE') 
      {
      $this->CoverageFile = new CoverageFile();
      $this->Coverage = new Coverage();
      $this->CoverageFile->FullPath = $attributes['FULLPATH'];
      if($attributes['COVERED'] == 1 || $attributes['COVERED']=="true")
        {
        $this->Coverage->Covered = 1;
        }
      else
        {
        $this->Coverage->Covered = 0;
        }  
      $this->Coverage->CoverageFile = $this->CoverageFile;
      }
    } // start element
  
  /** End element */
  public function endElement($parser, $name)
    {
    parent::endElement($parser, $name);
    if($name == 'SITE')
      {
      $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
      $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

      $this->Build->ProjectId = $this->projectid;
      $buildid = $this->Build->GetIdFromName();

      // If the build doesn't exist we add it
      if($buildid==0)
        {
        $this->Build->ProjectId = $this->projectid;
        $this->Build->StartTime = $start_time;
        $this->Build->EndTime = $end_time;
        $this->Build->SubmitTime = gmdate(FMT_DATETIME);
        add_build($this->Build);   
        $buildid = $this->Build->Id;
        }
      
      // Remove any previous coverage information
      $this->CoverageSummary->BuildId=$buildid;
      $this->CoverageSummary->RemoveAll();
      
      // Insert coverage summary
      $this->CoverageSummary->Insert();
      $this->CoverageSummary->ComputeDifference();
      
      foreach($this->CoverageSummary->GetCoverages() as $coverage)
        {
        $fileid = $coverage->CoverageFile->Id;
        $fullpath = $coverage->CoverageFile->FullPath;
        $loctested = $coverage->LocTested;
        $locuntested = $coverage->LocUntested;
        $branchstested = $coverage->BranchesTested;
        $branchsuntested = $coverage->BranchesUntested;
        $functionstested = $coverage->FunctionsTested;
        $functionsuntested = $coverage->FunctionsUntested;

        // Send an email if the coverage is below the project threshold
        //send_coverage_email($buildid,$fileid,$fullpath,$loctested,$locuntested,$branchstested,
        //                    $branchsuntested,$functionstested,$functionsuntested);
        }
  
      }
    else if($name=='FILE') 
      {
      $this->CoverageSummary->AddCoverage($this->Coverage);
      }
    } // end element
  
  /** Text function */
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();
    if($parent == 'COVERAGE')
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
        case 'LOCTESTED':
          $this->CoverageSummary->LocTested .= $data;
          break;
        case 'LOCUNTESTED':
          $this->CoverageSummary->LocUntested .= $data;
          break;
        }
      }
    else if($parent == 'FILE') 
      {
      switch ($element)
        {
        case 'LOCTESTED':
          $this->Coverage->LocTested .= $data;
          break;
        case 'LOCUNTESTED':
          $this->Coverage->LocUntested .= $data;
          break;
        case 'BRANCHESTESTED':
          $this->Coverage->BranchesTested .= $data;
          break;
        case 'BRANCHESUNTESTED':
          $this->Coverage->BranchesUntested .= $data;
          break;
        case 'FUNCTIONSTESTED':
          $this->Coverage->FunctionsTested .= $data;
          break;
        case 'FUNCTIONSUNTESTED':
          $this->Coverage->FunctionsUntested .= $data;
          break;    
        }
      }
    } // end text function
}
?>
