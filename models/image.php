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
class Image
{
    public $Id;
    public $Filename;
    public $Extension;
    public $Checksum;

    public $Data; // In the file refered by Filename
    public $Name; // Use to track the role for test

    public function __construct()
    {
        $this->Filename = '';
        $this->Name = '';
    }

    private function GetData()
    {
        if (strlen($this->Filename) > 0) {
            $h = fopen($this->Filename, 'rb');
            $this->Data = addslashes(fread($h, filesize($this->Filename)));
            fclose($h);
            unset($h);
        }
    }

    /** Check if exists */
    public function Exists()
    {
        // If no id specify return false
        if ($this->Id) {
            $query = pdo_query("SELECT count(*) AS c FROM image WHERE id='" . $this->Id . "'");
            $query_array = pdo_fetch_array($query);
            if ($query_array['c'] == 0) {
                return false;
            }
            return true;
        } else {
            // Check if the checksum exists
            $query = pdo_query("SELECT id FROM image WHERE checksum='" . $this->Checksum . "'");
            if (pdo_num_rows($query) > 0) {
                $query_array = pdo_fetch_array($query);
                $this->Id = $query_array['id'];
                return true;
            }
            return false;
        }
        return true;
    }

    /** Save the image */
    public function Save()
    {
        // Get the data from the file if necessary
        $this->GetData();

        if (!$this->Exists()) {
            $pdo = get_link_identifier()->getPdo();
            $success = true;
            if ($this->Id) {
                $stmt = $pdo->prepare('
                        INSERT INTO image (id, img, extension, checksum)
                        VALUES (:id, :img, :extension, :checksum)');
                $stmt->bindParam(':id', $this->Id);
                $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
                $stmt->bindParam(':extension', $this->Extension);
                $stmt->bindParam(':checksum', $this->Checksum);
                $success = $stmt->execute();
            } else {
                $stmt = $pdo->prepare('
                        INSERT INTO image (img, extension, checksum)
                        VALUES (:img, :extension, :checksum)');
                $stmt->bindParam(':img', $this->Data, PDO::PARAM_LOB);
                $stmt->bindParam(':extension', $this->Extension);
                $stmt->bindParam(':checksum', $this->Checksum);
                $success = $stmt->execute();
                $this->Id = pdo_insert_id('image');
            }
            if (!$success) {
                add_last_sql_error('Image::Save');
                return false;
            }
        }
        return true;
    }
}
