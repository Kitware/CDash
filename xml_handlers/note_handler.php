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
require_once('models/buildnote.php');

class NoteHandler extends AbstractHandler
{
    private $BuildId;
    private $Note;

  /** Constructor */
  public function __construct($projectID, $scheduleID)
  {
      parent::__construct($projectID, $scheduleID);
      $this->Build = new Build();
      $this->Site = new Site();
      $this->Configure = new BuildConfigure();
  }

  /** startElement function */
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
          $buildInformation = new BuildInformation();

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
      } elseif ($name=='NOTE') {
          $this->Note = new BuildNote();
          $this->Note->Name = isset($attributes['NAME'])?$attributes['NAME']:'';
      } elseif ($name=='NOTES') {
          // begining notes

      // We need a build to add notes (and cannot create one from the information)
      $this->Build->ProjectId = $this->projectid;
          $buildid = $this->Build->GetIdFromName($this->SubProjectName);
          $this->BuildId = $buildid;
      }
  } // end startElement

  /** endElement function */
  public function endElement($parser, $name)
  {
      parent::endElement($parser, $name);
      if ($name=='NOTE') {
          if ($this->BuildId>0) {
              // Insert the note
        $this->Note->BuildId = $this->BuildId;
              $this->Note->Insert();
          } else {
              add_log("note_handler.php", "Trying to add a note to an unexisting build");
          }
      }
  } // end endElement

  /** text function */
  public function text($parser, $data)
  {
      $parent = $this->getParent();
      $element = $this->getElement();
      if ($parent == 'NOTE') {
          switch ($element) {
        case 'DATETIME':
          $this->Note->Time = gmdate(FMT_DATETIME, str_to_time($data, $this->Build->GetStamp()));
          break;
        case 'TEXT':
          $this->Note->Text .= $data;
          break;
        }
      }
  } // end function text
} // end class;
