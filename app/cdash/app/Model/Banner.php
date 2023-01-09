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

class Banner
{
    private $ProjectId;
    private $Text;
    private $PDO;

    public function __construct()
    {
        $this->ProjectId = -1;
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Return the text */
    public function GetText()
    {
        $stmt = $this->PDO->prepare(
            'SELECT text FROM banner WHERE projectid = ?');
        if (!pdo_execute($stmt, [$this->ProjectId])) {
            return false;
        }
        $this->Text = $stmt->fetchColumn();
        if (strlen($this->Text) == 0) {
            return false;
        }
        return $this->Text;
    }

    /** Set the project id */
    public function SetProjectId($projectid)
    {
        $this->ProjectId = $projectid;
    }

    /** Return if exists */
    public function Exists()
    {
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM banner WHERE projectid = ?');
        if (!pdo_execute($stmt, [$this->ProjectId])) {
            return false;
        }
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            return true;
        }
        return false;
    }

    // Save the banner in the database
    public function SetText($text)
    {
        if ($this->ProjectId == -1) {
            add_log('No ProjectId specified', 'Banner::SetText', LOG_ERR);
            return false;
        }

        $this->Text = $text;

        // Check if the project is already
        if ($this->Exists()) {
            // Change the banner for this project.
            $stmt = $this->PDO->prepare(
                'UPDATE banner SET text = ? WHERE projectid = ?');
            if (!pdo_execute($stmt, [$this->Text, $this->ProjectId])) {
                return false;
            }
        } else {
            // Insert a banner for this project.
            $stmt = $this->PDO->prepare(
                'INSERT INTO banner (projectid, text) VALUES (?, ?)');
            if (!pdo_execute($stmt, [$this->ProjectId, $this->Text])) {
                return false;
            }
        }
        return true;
    }
}
