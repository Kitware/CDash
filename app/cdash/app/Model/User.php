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

use App\Models\Password;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class User
{
    public $Id;
    public $Email;
    public $Password;
    public $FirstName;
    public $LastName;
    public $Institution;
    public $Admin;
    private $Filled;
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
        $this->PDO = Database::getInstance();
        $this->Credentials = null;
        $this->LabelCollection = collect();
    }

    /** Return if a user exists */
    private function Exists(): bool
    {
        if (!$this->Id) {
            // If no id is set check if a user with this email address exists.
            if (strlen($this->Email) == 0) {
                return false;
            }

            // Check if the email is already there
            $user = \App\Models\User::firstWhere('email', $this->Email);
            if ($user !== null) {
                $this->Id = $user->id;
                return true;
            }
            return false;
        }

        return \App\Models\User::where('id', (int) $this->Id)
            ->orWhere([
                ['firstname', $this->FirstName],
                ['lastname', $this->LastName],
            ])->exists();
    }

    /** Save the user in the database */
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
                "UPDATE users SET
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

            if ($this->Id) {
                throw new InvalidArgumentException('Id set before user insert operation.');
            }

            $this->Id = DB::table('users')
                ->insertGetId([
                    'email' => $this->Email,
                    'password' => $this->Password,
                    'firstname' => $this->FirstName,
                    'lastname' => $this->LastName,
                    'institution' => $this->Institution,
                    'admin' => $this->Admin,
                ]);
            $this->RecordPassword();
        }
        return true;
    }

    /** Get the password.  Assumes the user exists. */
    private function GetPassword(): string|false
    {
        if (!$this->Id) {
            return false;
        }

        return \App\Models\User::findOrFail((int) $this->Id)->password;
    }

    /** Load this user's details from the database. */
    public function Fill(): bool
    {
        if (!$this->Id) {
            return false;
        }
        if ($this->Filled) {
            // Already filled, no need to do it again.
            return false;
        }

        $model = \App\Models\User::find((int) $this->Id);

        if ($model === null) {
            return false;
        }

        $this->Email = $model->email;
        $this->Password = $model->password;
        $this->FirstName = $model->firstname;
        $this->LastName = $model->lastname;
        $this->Institution = $model->institution;
        $this->Admin = $model->admin ? 1 : 0;

        $this->Filled = true;
        return true;
    }

    /** Record this user's password for the purposes of password rotation.
      * Does nothing if this feature is disabled.
      */
    private function RecordPassword(): void
    {
        if (config('cdash.password.expires') < 1 || !$this->Id || !$this->Password) {
            return;
        }

        $user_passwords = \App\Models\User::findOrFail((int) $this->Id)->passwords();
        $user_passwords->insert([
            'userid' => (int) $this->Id,
            'password' => $this->Password,
        ]);


        $unique_password_limit = config('cdash.password.unique');
        if ($unique_password_limit > 0) {
            // Delete old records for this user if they have more than
            // our limit.
            // Check if there are too many old passwords for this user.
            if ($user_passwords->count() > $unique_password_limit) {
                // If so, get the cut-off date so we can delete the rest.
                // TODO: This could be simplified into a single query.
                $cutoff = Password::where('userid', (int) $this->Id)
                    ->orderBy('date', 'desc')
                    ->offset((int) $unique_password_limit - 1)
                    ->firstOrFail()
                    ->date;

                // Then delete the ones that are too old
                Password::where([
                    ['userid', '=', (int) $this->Id],
                    ['date', '<', $cutoff],
                ])->delete();
            }
        }
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
                    $this->AddLabel($label);
                }
            }
        }
        return $this->LabelCollection;
    }

    /**
     * Given a $label, the $label is added to the LabelCollection.
     */
    public function AddLabel(Label $label): void
    {
        $this->LabelCollection->put($label->Text, $label);
    }
}
