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
use CDash\ServiceContainer;

/** BuildRelationship class */
class BuildRelationship
{
    public $Build;
    public $RelatedBuild;
    public $Project;
    public $Relationship;
    private $Filled;
    private $PDO;
    private $Service;

    public function __construct()
    {
        $this->Build = null;
        $this->RelatedBuild = null;
        $this->Project = null;
        $this->Relationship = '';
        $this->Filled = false;
        $this->PDO = Database::getInstance();
        $this->Service = ServiceContainer::getInstance();
    }

    /** Return true if a relationship already exists between these two builds */
    public function Exists()
    {
        if (!$this->Build || !$this->RelatedBuild) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM related_builds
             WHERE buildid = :buildid AND relatedid = :relatedid');
        $params = [
            ':buildid' => $this->Build->Id,
            ':relatedid' => $this->RelatedBuild->Id];
        pdo_execute($stmt, $params);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }

    /** Insert a new record in the database or update an existing one. */
    public function Save(&$error_msg)
    {
        $required_params =
            ['Build', 'RelatedBuild', 'Project', 'Relationship'];
        foreach ($required_params as $param) {
            if (!$this->$param) {
                $error_msg = "$param not set";
                add_log($error_msg, 'BuildRelationship::Save', LOG_ERR);
                return false;
            }
        }

        $builds = [$this->Build, $this->RelatedBuild];
        foreach ($builds as $build) {
            if (!$build->Exists()) {
                $error_msg = "Build #{$build->Id} does not exist";
                add_log($error_msg, 'BuildRelationship::Save',
                    LOG_ERR, $this->Project->Id, $build->Id);
                return false;
            }

            $build->FillFromId($build->Id);
            if ($build->ProjectId != $this->Project->Id) {
                $error_msg = 'Build does not belong to this project';
                add_log($error_msg,
                    'BuildRelationship::Save', LOG_ERR, $this->Project->Id,
                    $build->Id);
                return false;
            }
        }

        if ($this->Build->Id == $this->RelatedBuild->Id) {
            $error_msg = 'A build cannot be related to itself';
            add_log($error_msg,
                'BuildRelationship::Save', LOG_ERR, $this->Project->Id,
                $this->Build->Id);
        }

        if ($this->Exists()) {
            $stmt = $this->PDO->prepare(
                'UPDATE related_builds
                SET relationship = :relationship
                WHERE buildid = :buildid AND relatedid = :relatedid');
        } else {
            $stmt = $this->PDO->prepare(
                'INSERT INTO related_builds
                (buildid, relatedid, relationship)
                VALUES
                (:buildid, :relatedid, :relationship)');
        }
        $stmt->bindParam(':buildid', $this->Build->Id);
        $stmt->bindParam(':relatedid', $this->RelatedBuild->Id);
        $stmt->bindParam(':relationship', $this->Relationship);
        if (!pdo_execute($stmt)) {
            return false;
        }
        return true;
    }

    /** Delete this record from the database. */
    public function Delete(&$error_msg)
    {
        $required_params = ['Build', 'RelatedBuild'];
        foreach ($required_params as $param) {
            if (!$this->$param) {
                $error_msg = "$param not set";
                add_log($error_msg, 'BuildRelationship::Delete', LOG_ERR);
                return false;
            }
        }
        if (!$this->Exists()) {
            $error_msg = 'Relationship does not exist';
            add_log($error_msg, 'BuildRelationship::Delete',
                LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'DELETE FROM related_builds WHERE buildid = ? AND relatedid = ?');
        return pdo_execute($stmt, [$this->Build->Id, $this->RelatedBuild->Id]);
    }

    /** Retrieve details for a given token. */
    public function Fill()
    {
        if ($this->Filled) {
            return true;
        }
        $required_params = ['Build', 'RelatedBuild'];
        foreach ($required_params as $param) {
            if (!$this->$param) {
                add_log("$param not set", 'BuildRelationship::Fill', LOG_ERR);
                return false;
            }
        }

        $stmt = $this->PDO->prepare(
            'SELECT relationship FROM related_builds
             WHERE buildid = ? AND relatedid = ?');
        if (!pdo_execute($stmt, [$this->Build->Id, $this->RelatedBuild->Id])) {
            return false;
        }

        $row = $stmt->fetch();
        $this->Relationship = $row['relationship'];
        $this->Filled = true;
        return true;
    }

    /** Return an array representing this object. */
    public function marshal()
    {
        return [
            'buildid'      => $this->Build->Id,
            'relatedid'    => $this->RelatedBuild->Id,
            'relationship' => $this->Relationship,
        ];
    }

    /** Return marshaled arrays for all the builds related to this one. */
    public function GetRelationships($build)
    {
        $response = [];
        $stmt = $this->PDO->prepare(
            'SELECT relatedid, relationship, b.name
            FROM related_builds
            JOIN build b ON b.id = relatedid
            WHERE buildid = ?');
        if (!pdo_execute($stmt, [$build->Id])) {
            return false;
        }
        $response['from'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->PDO->prepare(
            'SELECT buildid, relationship, b.name
            FROM related_builds
            JOIN build b ON b.id = buildid
            WHERE relatedid = ?');
        if (!pdo_execute($stmt, [$build->Id])) {
            return false;
        }
        $response['to'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $response;
    }
}
