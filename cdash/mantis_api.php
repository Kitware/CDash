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

/** 
 * Handles calls to the Mantis Web API (MantisConnect)
 * Example construction: 
 *   $client = new MantisSoapClient("http://www.cmake.org/Bug/api/soap/mantisconnect.php?wsdl", "user", "password")
 */
class MantisSoapClient
{
  var $soapclient;
  var $username;
  var $password;
  
  function MantisSoapClient($wsdl_url, $username, $password)
  {
    $this->username = $username;
    $this->password = $password;
    //we copy the file locally since SoapClient constructor fails with post data for some reason
    if(!file_exists("../temp/mantis_connect.wsdl"))
      {
      mkdir("../temp");
      copy($wsdl_url, "../temp/mantis_connect.wsdl");
      }
    $this->soapclient = new SoapClient("../temp/mantis_connect.wsdl", array('classmap' => array('IssueData' => 'MantisIssue')));
  }
  
  function getIssueById($id)
  {
    return $this->soapclient->mc_issue_get($this->username, $this->password, $id);
  }
  
  function addNoteToIssue($id, $text)
  {
    $this->soapclient->mc_issue_note_add($this->username, $this->password, $id, 
      array('text'=>$text));
  }
  
  function getMantisConnectVersion()
  {
    return $this->soapclient->mc_version();
  }
}

class MantisIssue
{
  public $notes;
  public $summary;
  public $category;
  public $attachments;
  public $id;
  public $view_state;
  public $last_updated;
  public $status;
  public $severity;
  public $priority;
  public $description;
  public $additional_information;
  public $steps_to_reproduce;
  public $version;
  public $project;
  public $reproducibility;
  public $fixed_in_version;
  public $handler;
  public $relationships;
  public $os;
  public $os_build;
  public $date_submitted;
  public $eta;
  public $build;
  public $platform;
  
  //can put issue read methods here.  This class is currently provided for documentation of the
  //web service return type.
}

?>
