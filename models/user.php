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
// It is assumed that appropriate headers should be included before including this file
include_once('models/userproject.php');

class user
{
    public $Id;
    public $Email;
    public $Password;
    public $FirstName;
    public $LastName;
    public $Institution;
    public $Admin;

  /** Add a project to the user */
  public function AddProject($project)
  {
      $project->UserId = $this->Id;
      $project->Save();
  }

  /** Return if the user is admin */
  public function IsAdmin()
  {
      if (!$this->Id || !is_numeric($this->Id)) {
          return false;
      }
      $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='".$this->Id."'"));
      if ($user_array['admin'] == 1) {
          return true;
      }
      return false;
  }

  /** Return if a user exists */
  public function Exists()
  {
      // If no id specify return false
    if (!$this->Id) {
        if (strlen($this->Email) == 0) {
            return false;
        }

      // Check if the email is already there
      $query = pdo_query("SELECT count(*) FROM ".qid("user")." WHERE email='".$this->Email."'");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0]>0) {
            return true;
        }

        return false;
    }

      $query = pdo_query("SELECT count(*) FROM ".qid("user")." WHERE id='".$this->Id."' OR (firstname='".$this->FirstName."' AND lastname='".$this->LastName."')");
      $query_array = pdo_fetch_array($query);
      if ($query_array[0]>0) {
          return true;
      }
      return false;
  }

  // Save the user in the database
  public function Save()
  {
      if (empty($this->Admin)) {
          $this->Admin = 0;
      }

    // Check if the user exists already
    if ($this->Exists()) {
        // Update the project
      $query = "UPDATE ".qid("user")." SET";
        $query .= " email='".$this->Email."'";
        $query .= ",password='".$this->Password."'";
        $query .= ",firstname='".$this->FirstName."'";
        $query .= ",lastname='".$this->LastName."'";
        $query .= ",institution='".$this->Institution."'";
        $query .= ",admin='".$this->Admin."'";
        $query .= " WHERE id='".$this->Id."'";
        if (!pdo_query($query)) {
            add_last_sql_error("User Update");
            return false;
        }
    } else {
        // insert

      $id = "";
        $idvalue = "";
        if ($this->Id) {
            $id = "id,";
            $idvalue = "'".$this->Id."',";
        }

        $email = pdo_real_escape_string($this->Email);
        $passwd = pdo_real_escape_string($this->Password);
        $fname = pdo_real_escape_string($this->FirstName);
        $lname = pdo_real_escape_string($this->LastName);
        $institution = pdo_real_escape_string($this->Institution);

        $query = "INSERT INTO ".qid("user")." (".$id."email,password,firstname,lastname,institution,admin)
                 VALUES (".$idvalue."'".$email."','".$passwd."','".$fname."','".$lname."','".$institution."','$this->Admin')";
        if (!pdo_query($query)) {
            add_last_sql_error("User Create");
            return false;
        }

        if (!$this->Id) {
            $this->Id = pdo_insert_id("user");
        }
    }
      return true;
  }

  /** Get the name */
  public function GetName()
  {
      // If no id specify return false
    if (!$this->Id) {
        return false;
    }

      $query = pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id=".qnum($this->Id));
      $query_array = pdo_fetch_array($query);

      return trim($query_array['firstname']." ".$query_array['lastname']);
  }

  /** Get the email */
  public function GetEmail()
  {
      // If no id specify return false
    if (!$this->Id) {
        return false;
    }

      $query = pdo_query("SELECT email FROM ".qid("user")." WHERE id=".qnum($this->Id));
      $query_array = pdo_fetch_array($query);

      return $query_array['email'];
  }

  /** Set a password */
  public function SetPassword($newPassword)
  {
      if (!$this->Id || !is_numeric($this->Id)) {
          return false;
      }
      $query = pdo_query("UPDATE ".qid("user")." SET password='".$newPassword."' WHERE id='".$this->Id."'");
      if (!$query) {
          add_last_sql_error("User:SetPassword");
          return false;
      }
      return true;
  }

  /** Get the user id from the name */
  public function GetIdFromName($name)
  {
      $query = pdo_query("SELECT id FROM ".qid("user")." WHERE firstname='".$name."' OR lastname='".$name."'");
      if (!$query) {
          add_last_sql_error("User:GetIdFromName");
          return false;
      }

      if (pdo_num_rows($query)==0) {
          return false;
      }

      $query_array = pdo_fetch_array($query);
      return $query_array['id'];
  }

  /** Get the user id from the email */
  public function GetIdFromEmail($email)
  {
      $email = pdo_real_escape_string($email);
      $query = pdo_query("SELECT id FROM ".qid("user")." WHERE email='".trim($email)."'");
      if (!$query) {
          add_last_sql_error("User:GetIdFromEmail");
          return false;
      }

      if (pdo_num_rows($query)==0) {
          return false;
      }

      $query_array = pdo_fetch_array($query);
      return $query_array['id'];
  }
}
