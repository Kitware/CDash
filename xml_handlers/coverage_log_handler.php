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

class CoverageLogHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $CoverageFile;
    private $CoverageFileLog;
    private $BuildId;

  /** Constructor */
  public function __construct($projectID, $scheduleID)
  {
      parent::__construct($projectID, $scheduleID);
      $this->Build = new Build();
      $this->Site = new Site();
      $this->BuildId = 0;
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
          $this->Build->SiteId = $this->Site->Id;
          $this->Build->Name = $attributes['BUILDNAME'];
          if (empty($this->Build->Name)) {
              $this->Build->Name = "(empty)";
          }
          $this->Build->SetStamp($attributes['BUILDSTAMP']);
          $this->Build->Generator = $attributes['GENERATOR'];
      } elseif ($name=='FILE') {
          $this->CoverageFile = new CoverageFile();
          $this->CoverageFileLog = new CoverageFileLog();
          $this->CoverageFile->FullPath = $attributes['FULLPATH'];
      } elseif ($name=='LINE') {
          if ($attributes['COUNT']>=0) {
              $this->CoverageFileLog->AddLine($attributes['NUMBER'], $attributes['COUNT']);
          }
      }
  } // end startElement()

  /** End Element */
  public function endElement($parser, $name)
  {
      $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

      if ($name == "STARTDATETIME" && $parent == 'COVERAGELOG') {
          $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
          $this->Build->ProjectId = $this->projectid;
          $this->BuildId = $this->Build->GetIdFromName($this->SubProjectName);
          if ($this->BuildId == 0) {
              $t = 'Cannot add a coverage log to a build that does not exist';
              $f = 'CoverageLogHandler::endElement';
              add_log($t, $f, LOG_ERR, $this->projectid);
          }
      } elseif ($name == 'LINE') {
          $this->CoverageFile->File .= '<br>'; // cannot be <br/> for backward compatibility
      } elseif ($name == 'FILE') {
          if ($this->BuildId != 0) {
              $this->CoverageFile->Update($this->BuildId);
              $this->CoverageFileLog->BuildId = $this->BuildId;
              $this->CoverageFileLog->FileId = $this->CoverageFile->Id;
              $this->CoverageFileLog->Insert();
          }
          unset($this->CoverageFile);
          unset($this->CoverageFileLog);
      }
  } // end endElement()

  /** Text */
  public function text($parser, $data)
  {
      $parent = $this->getParent();
      $element = $this->getElement();
      if ($element == 'LINE') {
          $this->CoverageFile->File .= $data;
      }
  } // end text()
} // end class;

