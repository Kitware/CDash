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
require_once('xml_handlers/abstract_handler.php');
require_once('models/build.php');
require_once('models/site.php');
require_once('models/buildfailure.php');

class BuildHandler extends AbstractHandler
{
  private $StartTimeStamp;
  private $EndTimeStamp;
  private $Error;
  private $Append;

  public function __construct($projectid)
    {
    parent::__construct($projectid);
    $this->Build = new Build();
    $this->Site = new Site();
    $this->Append = false;
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

      if (array_key_exists('APPEND', $attributes))
        {
        if(strtolower($attributes['APPEND']) == "true")
          {
          $this->Append = true;
          }
        }
      else
        {
        $this->Append = false;
        }
      }
    else if($name=='WARNING') 
      {
      $this->Error = new BuildError();
      $this->Error->Type = 1;
      } 
    else if($name=='ERROR') 
      {
      $this->Error = new BuildError();
      $this->Error->Type = 0;
      }
    else if($name=='FAILURE') 
      {
      $this->Error = new BuildFailure();
      $this->Error->Type = 0;
      if($attributes['TYPE']=="Error")
        {
        $this->Error->Type = 0;
        }
      else if($attributes['TYPE']=="Warning")
        {
        $this->Error->Type = 1;
        }
      }
    }

  public function endElement($parser, $name)
    {
    parent::endElement($parser, $name);

    if($name == 'BUILD')
      {    
      $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
      $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
      $submit_time = gmdate(FMT_DATETIME);

      $this->Build->ProjectId = $this->projectid;
      $this->Build->StartTime = $start_time;
      $this->Build->EndTime = $end_time;
      $this->Build->SubmitTime = $submit_time;
      $this->Build->SetSubProject($this->SubProjectName);
      $this->Build->Append = $this->Append;

      add_build($this->Build);
      }
    else if($name=='WARNING' || $name=='ERROR' || $name=='FAILURE') 
      {
      $this->Build->AddError($this->Error);
      } 
    }
  
  public function text($parser, $data)
    {
    $parent = $this->getParent();
    $element = $this->getElement();
    if($parent == 'BUILD')
      {
      switch ($element)
        {
        case 'STARTBUILDTIME':
          $this->StartTimeStamp = $data;
          break;
        case 'STARTDATETIME':
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
          break;
        case 'ELAPSEDMINUTES':
          $this->EndTimeStamp = $this->StartTimeStamp+$data*60;
          break;
        case 'ENDDATETIME':
          $this->EndTimeStamp = $data;
          break;
        case 'BUILDCOMMAND':
          $this->Build->Command = $data;
          break;
        case 'LOG':
          $this->Build->Log .= $data;
          break;
        }
      } 
    else if($parent == 'ACTION')
      {
      switch ($element)
        {
        case 'LANGUAGE':
          $this->Error->Language .= $data;
          break;
        case 'SOURCEFILE':
          $this->Error->SourceFile .= $data;
          break;
        case 'TARGETNAME':
          $this->Error->TargetName .= $data;
          break;
        case 'OUTPUTFILE':
          $this->Error->OutputFile .= $data;
          break;
        case 'OUTPUTTYPE':
          $this->Error->OutputType .= $data;
          break;
        }
      }  
    else if($parent == 'COMMAND')
      {
      switch ($element)
        {
        case 'WORKINGDIRECTORY':
          $this->Error->WorkingDirectory .= $data;
          break;
        case 'ARGUMENT':
          if(strlen($this->Error->Arguments)>0)
            {
            $this->Error->Arguments .= ' ';
            } 
          $this->Error->Arguments  .= $data;
          break;
        }
      }  
    else if($parent == 'RESULT')
      {
      switch ($element)
        {
        case 'STDOUT':
          $this->Error->StdOutput .= $data;
          break;
        case 'STDERR':
          $this->Error->StdError .= $data;
          break;
        case 'EXITCONDITION':
          $this->Error->ExitCondition .= $data;
          break;  
        }
      }    
    else if($element == 'BUILDLOGLINE')
      {
      $this->Error->Logline .= $data;
      }
    else if($element == 'TEXT')
      {
      $this->Error->Text .= $data;
      }
    else if($element == 'SOURCEFILE') 
      {
      $this->Error->SourceFile .= $data;
      }
    else if($element == 'SOURCELINENUMBER') 
      {
      $this->Error->SourceLine .= $data;
      } 
    else if($element == 'PRECONTEXT') 
      {
      $this->Error->PreContext .= $data;
      } 
    else if($element == 'POSTCONTEXT') 
      {
      $this->Error->PostContext .= $data;
      }
    } // end function text
  } // end class
?>
