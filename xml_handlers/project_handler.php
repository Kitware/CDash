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
require_once('models/project.php');
require_once('models/subproject.php');

class ProjectHandler extends AbstractHandler
{
  private $SubProject;
  
  /** Constructor */
  public function __construct($projectid)
    {
    parent::__construct($projectid);
    }
  
  /** startElement function */
  public function startElement($parser, $name, $attributes)
    {
    parent::startElement($parser, $name, $attributes);
    // Check that the project name is correct
    if($name=='PROJECT')
      {
      if(get_project_id($attributes['NAME']) != $this->projectid)
        {
        add_log("Wrong project name","ProjectHandler:startElement");
        exit();
        }
      }
    else if($name=='SUBPROJECT')
      {
      $this->SubProject = new SubProject();
      $this->SubProject->SetProjectId($this->projectid);
      $this->SubProject->Name = $attributes['NAME'];
      $this->SubProject->Save();
      }
    else if($name=='DEPENDENCY') 
      {
      $dependentProject = new SubProject();
      $dependentProject->Name = $attributes['NAME'];
      $dependentProject->SetProjectId($this->projectid);
      $this->SubProject->AddDependency($dependentProject->GetIdFromName());
      }
    } // end startElement
  
  /** endElement function */
  public function endElement($parser, $name)
    {
    parent::endElement($parser, $name);
    /*if($name=='DEPENDENCY')
      {  
      }*/
   } // end endElement

  /** text function */
  public function text($parser, $data)
    {
    //$parent = $this->getParent();
    //$element = $this->getElement();
    } // end function text
} // end class
?>
