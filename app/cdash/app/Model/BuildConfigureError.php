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

/** BuildConfigureError class */
class BuildConfigureError
{
    public $Type;
    public $Text;
    public $ConfigureId;
    private $PDO;

    public function __construct()
    {
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Return if exists */
    public function Exists()
    {
        if (!$this->ConfigureId || !is_numeric($this->ConfigureId)) {
            add_log('ConfigureId not set',
                    'BuildConfigureError::Exists', LOG_ERR);
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            add_log('Type not set',
                    'BuildConfigureError::Exists', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) AS c FROM configureerror
            WHERE configureid = :configureid AND type = :type AND text = :text');
        $stmt->bindParam(':configureid', $this->ConfigureId);
        $stmt->bindParam(':type', $this->Type);
        $stmt->bindParam(':text', $this->Text);
        pdo_execute($stmt);
        $row = $stmt->fetch();
        if ($row['c'] > 0) {
            return true;
        }
        return false;
    }

    /** Save in the database */
    public function Save()
    {
        if (!$this->ConfigureId || !is_numeric($this->ConfigureId)) {
            add_log('ConfigureId not set',
                    'BuildConfigureError::Save', LOG_ERR);
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            add_log('Type not set',
                    'BuildConfigureError::Save', LOG_ERR);
            return false;
        }

        if (!$this->Exists()) {
            $stmt = $this->PDO->prepare(
                'INSERT INTO configureerror (configureid, type, text)
                VALUES (:configureid, :type, :text)');
            $stmt->bindParam(':configureid', $this->ConfigureId);
            $stmt->bindParam(':type', $this->Type);
            $stmt->bindParam(':text', $this->Text);
            if (!pdo_execute($stmt)) {
                return false;
            }
        }
        return true;
    }
}
