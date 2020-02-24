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
namespace CDash\Model;

use CDash\Config;
use CDash\Database;

require_once 'include/common.php';

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
    public $TableName;
    public $TempTableName;
    private $PDO;
    private $Credentials;
    private $LabelCollection;

    public function __construct()
    {
        $this->Id = null;
        $this->Email = '';
        $this->Password = '';
        $this->FirstName = '';
        $this->LastName = '';
        $this->Institution = '';
        $this->Admin = 0;
        $this->Filled = false;
        $this->TableName = qid('user');
        $this->TempTableName = qid('usertemp');
        $this->PDO = Database::getInstance();
        $this->Credentials = null;
        $this->LabelCollection = collect();
    }

    /** Return if the user is admin */
    public function IsAdmin()
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            "SELECT admin FROM $this->TableName WHERE id = ?");
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        if ($row && array_key_exists('admin', $row) && $row['admin'] == 1) {
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

        $stmt = $this->PDO->prepare(
            "SELECT COUNT(*) FROM $this->TableName WHERE id = :id OR
            (firstname = :firstname AND lastname = :lastname)");
        $stmt->bindParam(':id', $this->Id);
        $stmt->bindParam(':firstname', $this->FirstName);
        $stmt->bindParam(':lastname', $this->LastName);
        pdo_execute($stmt);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }

    /** Return if a temporary record for this user exists. */
    public function TempExists()
    {
        $stmt = $this->PDO->prepare(
            "SELECT COUNT(*) FROM $this->TempTableName WHERE email = ?");
        pdo_execute($stmt, [$this->Email]);
        if ($stmt->fetchColumn() > 0) {
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
            $stmt = $this->PDO->prepare(
                "UPDATE $this->TableName SET
                email = :email, password = :password, firstname = :firstname,
                lastname = :lastname, institution = :institution, admin = :admin
                WHERE id = :id");
            $stmt->bindParam(':email', $this->Email);
            $stmt->bindParam(':password', $this->Password);
            $stmt->bindParam(':firstname', $this->FirstName);
            $stmt->bindParam(':lastname', $this->LastName);
            $stmt->bindParam(':institution', $this->Institution);
            $stmt->bindParam(':admin', $this->Admin);
            $stmt->bindParam(':id', $this->Id);
            if (!pdo_execute($stmt)) {
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
                $id = 'id, ';
                $idvalue = ':id, ';
            }

            $stmt = $this->PDO->prepare(
                "INSERT INTO $this->TableName
                ($id email, password, firstname, lastname, institution, admin)
                VALUES ($idvalue :email, :password, :firstname, :lastname, :institution, :admin)");
            $stmt->bindParam(':email', $this->Email);
            $stmt->bindParam(':password', $this->Password);
            $stmt->bindParam(':firstname', $this->FirstName);
            $stmt->bindParam(':lastname', $this->LastName);
            $stmt->bindParam(':institution', $this->Institution);
            $stmt->bindParam(':admin', $this->Admin);
            if ($this->Id) {
                $stmt->bindParam(':id', $this->Id);
            }

            if (!pdo_execute($stmt)) {
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('user');
            }
            $this->RecordPassword();
        }
        return true;
    }

    // Save a temporary record for this user in the database.
    public function SaveTemp($key, $date)
    {
        $stmt = $this->PDO->prepare(
            "INSERT INTO $this->TempTableName
            (email, password, firstname, lastname, institution,
             registrationkey, registrationdate)
            VALUES
            (:email, :password, :firstname, :lastname, :institution,
             :registrationkey, :registrationdate)");
        $stmt->bindParam(':email', $this->Email);
        $stmt->bindParam(':password', $this->Password);
        $stmt->bindParam(':firstname', $this->FirstName);
        $stmt->bindParam(':lastname', $this->LastName);
        $stmt->bindParam(':institution', $this->Institution);
        $stmt->bindParam(':registrationkey', $key);
        $stmt->bindParam(':registrationdate', $date);
        return pdo_execute($stmt);
    }

    // Move a row from usertemp to user.
    public function Register($key)
    {
        $stmt = $this->PDO->prepare(
            "SELECT * FROM $this->TempTableName WHERE registrationkey = ?");
        if (!pdo_execute($stmt, [$key])) {
            return false;
        }
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->Email = $row['email'];
        $this->Password = $row['password'];
        $this->FirstName = $row['firstname'];
        $this->LastName = $row['lastname'];
        $this->Institution = $row['institution'];
        if ($this->Save()) {
            $stmt = $this->PDO->prepare(
                "DELETE FROM $this->TempTableName WHERE email = ?");
            pdo_execute($stmt, [$this->Email]);
            return true;
        }
        return false;
    }

    // Remove this user from the database.
    public function Delete()
    {
        if (!$this->Id) {
            return false;
        }
        $stmt = $this->PDO->prepare("DELETE FROM $this->TableName WHERE id = ?");
        pdo_execute($stmt, [$this->Id]);
    }

    /** Get the name */
    public function GetName()
    {
        // If no id specified return false.
        if (!$this->Id) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            "SELECT firstname, lastname FROM $this->TableName WHERE id = ?");
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        return trim($row['firstname'] . ' ' . $row['lastname']);
    }

    /** Get the email */
    public function GetEmail()
    {
        // If no id specified return false.
        if (!$this->Id) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            "SELECT email FROM $this->TableName WHERE id = ?");
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        return $row['email'];
    }

    /** Get the password */
    public function GetPassword()
    {
        if (!$this->Id) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            "SELECT password FROM $this->TableName WHERE id = ?");
        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        return $row['password'];
    }

    /** Get the user id from the name */
    public function GetIdFromName($name)
    {
        $stmt = $this->PDO->prepare(
            "SELECT id FROM $this->TableName
            WHERE firstname = :name OR lastname = :name");
        $stmt->bindParam(':name', $name);
        if (!pdo_execute($stmt)) {
            return false;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return $row['id'];
    }

    /** Get the user id from the email */
    public function GetIdFromEmail($email)
    {
        $email = trim($email);
        $stmt = $this->PDO->prepare(
            "SELECT id FROM $this->TableName WHERE email = :email");
        $stmt->bindParam(':email', $email);
        if (!pdo_execute($stmt)) {
            return false;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return $row['id'];
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

        $stmt = $this->PDO->prepare(
            "SELECT email, password, firstname, lastname, institution, admin
            FROM $this->TableName WHERE id = ?");
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        $row = $stmt->fetch();
        if (!$row || !array_key_exists('password', $row)) {
            return false;
        }

        $this->Email = $row['email'];
        $this->Password = $row['password'];
        $this->FirstName = $row['firstname'];
        $this->LastName = $row['lastname'];
        $this->Institution = $row['institution'];
        $this->Admin = 0;
        if ($row['admin'] == 1) {
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
        $config = Config::getInstance();

        if (config('cdash.password.expires') < 1 || !$this->Id || !$this->Password) {
            return false;
        }

        $now = gmdate(FMT_DATETIME);
        // Insert a row in the password table for this new password.
        $stmt = $this->PDO->prepare(
            'INSERT INTO password(userid, password, date)
            VALUES (:userid, :password, :date)');
        $stmt->bindParam(':userid', $this->Id);
        $stmt->bindParam(':password', $this->Password);
        $stmt->bindParam(':date', $now);
        pdo_execute($stmt);

        if ($config->get('CDASH_UNIQUE_PASSWORD_COUNT') > 0) {
            // Delete old records for this user if they have more than
            // our limit.
            // Check if there are too many old passwords for this user.
            $stmt = $this->PDO->prepare(
                'SELECT COUNT(*) AS numrows FROM password WHERE userid = ?');
            pdo_execute($stmt, [$this->Id]);
            $num_rows = $stmt->fetchColumn();
            if ($num_rows > $config->get('CDASH_UNIQUE_PASSWORD_COUNT')) {
                // If so, get the cut-off date so we can delete the rest.
                $offset = $config->get('CDASH_UNIQUE_PASSWORD_COUNT') - 1;
                $stmt = $this->PDO->prepare(
                    "SELECT date FROM password
                    WHERE userid = ?
                    ORDER BY date DESC
                    LIMIT 1 OFFSET $offset");
                pdo_execute($stmt, [$this->Id]);
                $row = $stmt->fetch();
                $cutoff = $row['date'];
                // Then delete the ones that are too old
                $stmt = $this->PDO->prepare(
                    'DELETE FROM password
                    WHERE userid= :userid AND date < :date');
                $stmt->bindParam(':userid', $this->Id);
                $stmt->bindParam(':date', $cutoff);
                pdo_execute($stmt);
            }
        }
    }

    /** Set the cookie key for the current user.
      */
    public function SetCookieKey($key)
    {
        if (!$this->Id) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            "UPDATE $this->TableName SET cookiekey = :key WHERE id = :id");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':id', $this->Id);
        return pdo_execute($stmt);
    }

    /** Populate $this from cookie values.
      */
    public function FillFromCookie($cookiekey, $cookieuserid)
    {
        $sql = "SELECT * FROM $this->TableName WHERE cookiekey = :cookiekey";
        if (!empty($cookieuserid)) {
            $sql .= ' AND id = :id';
        }
        $stmt = $this->PDO->prepare($sql);
        $stmt->bindParam(':cookiekey', $cookiekey);
        if (!empty($cookieuserid)) {
            $stmt->bindParam(':id', $cookieuserid);
        }
        if (!pdo_execute($stmt)) {
            return false;
        }
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $this->Id = $row['id'];
        $this->Email = $row['email'];
        $this->Password = $row['password'];
        $this->FirstName = $row['firstname'];
        $this->LastName = $row['lastname'];
        $this->Institution = $row['institution'];
        $this->Admin = $row['admin'];
        $this->Filled = true;
        return true;
    }

    public function hasExpiredPassword()
    {
        $expires = config('cdash.password.expires');

        if ($expires < 1) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT date FROM password WHERE userid = ?
        ORDER BY date DESC LIMIT 1');
        $this->PDO->execute($stmt, [$this->Id]);
        $row = $stmt->fetch();

        if (!$row) {
            // If no result, then password rotation must have been enabled
            // after this user set their password.  Force them to change it now.
            return true;
        }

        $password_created_time = strtotime($row['date']);
        $password_expiration_time =
            strtotime("+{$expires} days", $password_created_time);
        if (time() > $password_expiration_time) {
            return true;
        }
        return false;
    }

    /** Wrapper around PHP's builtin password_hash function.
      * Logs an error if it returns FALSE.
      */
    public static function PasswordHash($password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            add_log('password_hash returned false', 'PasswordHash', LOG_ERR);
        }
        return $passwordHash;
    }

    /**
     * Returns the current User's repository credentials. (There may be multiple credentials
     * for multiple repositories).
     *
     * @return array|bool|null
     */
    public function GetRepositoryCredentials()
    {
        if (is_null($this->Credentials)) {
            if (!$this->Id) {
                return false;
            }

            $sql = 'SELECT credential FROM user2repository WHERE userid = :id';
            $stmt = $this->PDO->prepare($sql);
            $stmt->bindParam(':id', $this->Id);
            if ($this->PDO->execute($stmt)) {
                $this->Credentials = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
        }
        return $this->Credentials;
    }

    /**
     * Return's the current User's LabelCollection. If a LabelCollection is not yet defined
     * this method checks the database for the labels of which a users has subscribed and
     * return's them wrapped in a LabelCollection.
     *
     * @return Collection
     */
    public function GetLabelCollection()
    {
        if ($this->LabelCollection->isEmpty()) {
            $sql = '
              SELECT label.id, label.text
              FROM labelemail
              JOIN label ON label.id = labelemail.labelid
              WHERE userid=:user';

            $stmt = $this->PDO->prepare($sql);
            $stmt->bindParam(':user', $this->Id);
            if ($this->PDO->execute($stmt)) {
                foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
                    $label = new Label();
                    $label->Id = $row->id;
                    $label->Text = $row->text;
                    $this->LabelCollection->push($label);
                }
            }
        }
        return $this->LabelCollection;
    }

    /**
     * Given a $label, the $label is added to the LabelCollection.
     *
     * @param Label $label
     */
    public function AddLabel(Label $label)
    {
        $this->LabelCollection->push($label);
    }
}
