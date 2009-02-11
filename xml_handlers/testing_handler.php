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
require_once('models/test.php');

//TODO: images handling!!!!
class TestingHandler extends AbstractHandler
{
  private $StartTimeStamp;
  private $EndTimeStamp;
  private $UpdateEndTime; // should we update the end time of the build

  private $BuildId;  
  private $Test;
  private $BuildTest;
  private $BuildTestDiff;
  private $TestImage;
  private $TestMeasurement;
  private $Label;
  private $Append;

  /** Constructor */
  public function __construct($projectID)
    {
    parent::__construct($projectID);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->UpdateEndTime = false;
    }

  /** Destructor */
  public function __destruct()
    {
    $this->Build->ComputeTestTiming();
    }
  
  /** Start Element */
  public function startElement($parser, $name, $attributes)
    {
    parent::startElement($parser, $name, $attributes);
    $parent = $this->getParent(); // should be before endElement
    
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

      if (array_key_exists('APPEND', $attributes))
        {
        $this->Append = $attributes['APPEND'];
        }
      else
        {
        $this->Append = false;
        }
      }
    else if($name == "TEST" && count($attributes) > 0)
      {
      $this->Test = new Test();
      $this->BuildTest = new BuildTest();
      $this->BuildTest->Status = $attributes['STATUS'];
      }
    else if($name == "NAMEDMEASUREMENT")
      {
      $this->TestMeasurement = new TestMeasurement();
      $this->TestMeasurement->Name = $attributes['NAME'];
      $this->TestMeasurement->Type = $attributes['TYPE'];
      }
    else if($name == 'LABEL' && $parent == 'LABELS')
      {
      $this->Label = new Label();
      }
    else if($name == "TESTLIST" && $parent == 'TESTING')
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
        $this->Build->SetSubProject($this->SubProjectName);
        $this->Build->Append = $this->Append;

        add_build($this->Build);

        $this->UpdateEndTime = true;  
        $buildid = $this->Build->Id;
        }
        
      $this->BuildId = $buildid;
      }  
    } // end startElement


  /** End Element */
  public function endElement($parser, $name)
    {
    $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);
     
    if($name == "TEST" && $parent == 'TESTING')
      {  
      $this->Test->Insert();
      if($this->Test->Id>0)
        {
        $this->BuildTest->TestId = $this->Test->Id;
        $this->BuildTest->BuildId = $this->BuildId;
        $this->BuildTest->Insert();
        }
      }
    else if($name == 'LABEL' && $parent == 'LABELS')
      {
      if(isset($this->Test))
        {
        $this->Test->AddLabel($this->Label);
        }
      }
    else if($name == "NAMEDMEASUREMENT")
      {
      if($this->TestMeasurement->Name == 'Execution Time')
        {
        $this->BuildTest->Time = $this->TestMeasurement->Value;
        } 
      else if($this->TestMeasurement->Name == 'Exit Code') 
        {
        $this->Test->Details = $this->TestMeasurement->Value;
        }
      else if($this->TestMeasurement->Name == 'Completion Status') 
        {
        $this->Test->Details = $this->TestMeasurement->Value;
        }
      else // explicit measurement
        {
        $this->Test->AddMeasurement($this->TestMeasurement);
        }    
      } // end named measurement
    else if($name == "SITE")
      {
      if($this->UpdateEndTime)
        {
        $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
        $this->Build->UpdateEndTime($end_time);
        }
      }
    } // end endElement
  
  /** Text function */
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();

    if($parent == 'TESTING' && $element == 'STARTDATETIME')
      {
      $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
      }
    else if($parent == 'TESTING' && $element == 'STARTTESTTIME')
      {
      $this->StartTimeStamp = $data;
      }
    else if($parent == 'TESTING' && $element == 'ENDDATETIME')
      {
      $this->EndTimeStamp = str_to_time($data, $this->Build->GetStamp());
      }  
    else if($parent == 'TESTING' && $element == 'ENDTESTTIME')
      {
      $this->EndTimeStamp = $data;
      }
    else if($parent == "TEST")
      {
      switch ($element)
        {
        case "NAME":
          $this->Test->Name .= $data;
        break;
        case "PATH":
          $this->Test->Path .= $data;
        break;
        case "FULLNAME":
          //$this->Test->Command = $data;
          break;
        case "FULLCOMMANDLINE":
          $this->Test->Command .= $data;
          break;
        }
      }
    else if($parent == "NAMEDMEASUREMENT" && $element == "VALUE")
      {
      $this->TestMeasurement->Value .= $data;
      }
    else if($parent == "MEASUREMENT" && $element == "VALUE")
      {
      $this->Test->Output .= $data;
      }
    else if($parent == 'LABELS' && $element == 'LABEL')
      {
      $this->Label->SetText($data);
      }
    }
}
?>
