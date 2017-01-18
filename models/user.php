<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

// It is assumed that appropriate headers should be included before including this file
include_once 'models/userproject.php';

class User
{
    public $Id;
    public $Email;
    public $Password;
    public $FirstName;
    public $LastName;
    public $Institution;
    public $Admin;
    public $Filled;

    public function __construct()
    {
        $this->Filled = false;
    }

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
        $user_array = pdo_fetch_array(pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='" . $this->Id . "'"));
        if ($user_array['admin'] == 1) {
            return true;
        }
        return false;
    }

    /** Return if a user exists */
    public function Exists()
    {
        if (!$this->Id) {
            // If no id is set check if a user with this email address exists.
            if (strlen($this->Email) == 0) {
                return false;
            }

            // Check if the email is already there
            $userid = $this->GetIdFromEmail($this->Email);
            if ($userid) {
                $this->Id = $userid;
                return true;
            }
            return false;
        }

        $query = pdo_query('SELECT count(*) FROM ' . qid('user') . " WHERE id='" . $this->Id . "' OR (firstname='" . $this->FirstName . "' AND lastname='" . $this->LastName . "')");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
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
            $oldPassword = $this->GetPassword();

            // Update the project
            $query = 'UPDATE ' . qid('user') . ' SET';
            $query .= " email='" . $this->Email . "'";
            $query .= ",password='" . $this->Password . "'";
            $query .= ",firstname='" . $this->FirstName . "'";
            $query .= ",lastname='" . $this->LastName . "'";
            $query .= ",institution='" . $this->Institution . "'";
            $query .= ",admin='" . $this->Admin . "'";
            $query .= " WHERE id='" . $this->Id . "'";
            if (!pdo_query($query)) {
                add_last_sql_error('User Update');
                return false;
            }
            if ($this->Password != $oldPassword) {
                $this->RecordPassword();
            }
        } else {
            // insert

            $id = '';
            $idvalue = '';
            if ($this->Id) {
                $id = 'id,';
                $idvalue = "'" . $this->Id . "',";
            }

            $email = pdo_real_escape_string($this->Email);
            $passwd = pdo_real_escape_string($this->Password);
            $fname = pdo_real_escape_string($this->FirstName);
            $lname = pdo_real_escape_string($this->LastName);
            $institution = pdo_real_escape_string($this->Institution);

            $query = 'INSERT INTO ' . qid('user') . ' (' . $id . 'email,password,firstname,lastname,institution,admin)
                 VALUES (' . $idvalue . "'" . $email . "','" . $passwd . "','" . $fname . "','" . $lname . "','" . $institution . "','$this->Admin')";
            if (!pdo_query($query)) {
                add_last_sql_error('User Create');
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('user');
            }
            $this->RecordPassword();
        }
        return true;
    }

    // Remove this user from the database.
    public function Delete()
    {
        if (!$this->Id) {
            return false;
        }
        $pdo = get_link_identifier()->getPdo();
        $user_table = qid('user');
        $stmt = $pdo->prepare("DELETE FROM $user_table WHERE id=?");
        pdo_execute($stmt, [$this->Id]);
    }

    /** Get the name */
    public function GetName()
    {
        // If no id specify return false
        if (!$this->Id) {
            return false;
        }

        $query = pdo_query('SELECT firstname,lastname FROM ' . qid('user') . ' WHERE id=' . qnum($this->Id));
        $query_array = pdo_fetch_array($query);
        return trim($query_array['firstname'] . ' ' . $query_array['lastname']);
    }

    /** Get the email */
    public function GetEmail()
    {
        // If no id specify return false
        if (!$this->Id) {
            return false;
        }

        $query = pdo_query('SELECT email FROM ' . qid('user') . ' WHERE id=' . qnum($this->Id));
        $query_array = pdo_fetch_array($query);
        return $query_array['email'];
    }

    /** Get the password */
    public function GetPassword()
    {
        if (!$this->Id) {
            return false;
        }

        $query = pdo_query('SELECT password FROM ' . qid('user') . ' WHERE id=' . qnum($this->Id));
        $query_array = pdo_fetch_array($query);
        return $query_array['password'];
    }

    /** Set a password */
    public function SetPassword($newPassword)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }
        $query = pdo_query('UPDATE ' . qid('user') . " SET password='" . $newPassword . "' WHERE id='" . $this->Id . "'");
        if (!$query) {
            add_last_sql_error('User:SetPassword');
            return false;
        }
        return true;
    }

    /** Get the user id from the name */
    public function GetIdFromName($name)
    {
        $query = pdo_query('SELECT id FROM ' . qid('user') . " WHERE firstname='" . $name . "' OR lastname='" . $name . "'");
        if (!$query) {
            add_last_sql_error('User:GetIdFromName');
            return false;
        }

        if (pdo_num_rows($query) == 0) {
            return false;
        }

        $query_array = pdo_fetch_array($query);
        return $query_array['id'];
    }

    /** Get the user id from the email */
    public function GetIdFromEmail($email)
    {
        $email = pdo_real_escape_string($email);
        $query = pdo_query('SELECT id FROM ' . qid('user') . " WHERE email='" . trim($email) . "'");
        if (!$query) {
            add_last_sql_error('User:GetIdFromEmail');
            return false;
        }

        if (pdo_num_rows($query) == 0) {
            return false;
        }

        $query_array = pdo_fetch_array($query);
        return $query_array['id'];
    }

    /** Load this user's details from the datbase. */
    public function Fill()
    {
        if (!$this->Id) {
            return false;
        }
        if ($this->Filled) {
            // Already filled, no need to do it again.
            return false;
        }

        $row = pdo_single_row_query('
                SELECT email, password, firstname, lastname, institution
                FROM ' . qid('user') . " WHERE id='$this->Id'");
        if (!$row || !array_key_exists('password', $row)) {
            return false;
        }


        $this->Email = $row['email'];
        $this->Password = $row['password'];
        $this->FirstName = $row['firstname'];
        $this->LastName = $row['lastname'];
        $this->Institution = $row['institution'];

        $this->Admin = 0;
        if ($this->IsAdmin()) {
            $this->Admin = 1;
        }

        $this->Filled = true;
        return true;
    }

    /** Record this user's password for the purposes of password rotation.
      * Does nothing if this feature is disabled.
      */
    public function RecordPassword()
    {
        global $CDASH_PASSWORD_EXPIRATION, $CDASH_UNIQUE_PASSWORD_COUNT;
        if ($CDASH_PASSWORD_EXPIRATION < 1 || !$this->Id || !$this->Password) {
            return false;
        }

        $now = gmdate(FMT_DATETIME);
        // Insert a row in the password table for this new password.
        pdo_query("
                INSERT INTO password(userid, password, date)
                VALUES ($this->Id, '$this->Password', '$now')");

        if ($CDASH_UNIQUE_PASSWORD_COUNT > 0) {
            // Delete old records for this user if they have more than
            // our limit.
            // Check if there are too many old passwords for this user.
            $row = pdo_single_row_query("SELECT COUNT(1) AS numrows
                    FROM password WHERE userid=$this->Id");
            $num_rows = $row['numrows'];
            if ($num_rows > $CDASH_UNIQUE_PASSWORD_COUNT) {
                // If so, get the cut-off date so we can delete the rest.
                $offset = $CDASH_UNIQUE_PASSWORD_COUNT - 1;
                $row = pdo_single_row_query("SELECT date FROM password
                        WHERE userid=$this->Id ORDER BY date DESC
                        LIMIT 1 OFFSET $offset");
                $cutoff = $row['date'];
                // Then delete the ones that are too old
                pdo_query("DELETE FROM password
                        WHERE userid=$this->Id AND date < '$cutoff'");
            }
        }
    }
}
