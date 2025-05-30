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

use CDash\Database;
use PDO;

/**
 * @deprecated 04/22/2025  Used only in the legacy notification system.  Use Eloquent for new work.
 */
class User
{
    public $Id;
    public $Email;
    public $Password;
    public $FirstName;
    public $LastName;
    public $Institution;
    public $Admin;
    private $PDO;
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
        $this->PDO = Database::getInstance();
        $this->LabelCollection = collect();
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
                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
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
