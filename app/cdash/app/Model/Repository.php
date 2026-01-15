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

use App\Models\BuildProperties;
use CDash\Database;
use CDash\Lib\Repository\GitHub;
use CDash\Lib\Repository\RepositoryInterface;
use CDash\Service\RepositoryService;
use Exception;
use ReflectionClass;

class Repository
{
    public const VIEWER_GITHUB = 'GitHub';
    public const VIEWER_GITLAB = 'GitLab';

    public static function getViewers(): array
    {
        $self = new ReflectionClass(__CLASS__);
        $viewers = [];
        foreach ($self->getConstants() as $key => $text) {
            if (str_starts_with($key, 'VIEWER_')) {
                $value = strtolower(substr($key, strlen('VIEWER_')));
                $viewers[$text] = $value;
            }
        }
        return $viewers;
    }

    public static function setStatus(Build $build, $complete = true): void
    {
        $buildProperties = BuildProperties::find((int) $build->Id);
        if ($buildProperties === null || !array_key_exists('status context', $buildProperties->properties)) {
            return;
        }

        $context = $buildProperties->properties['status context'];

        $project = new Project();
        $project->Id = $build->ProjectId;
        $project->Fill();
        $repositoryService = self::getRepositoryService($project);
        if ($repositoryService) {
            if ($complete) {
                $repositoryService->setStatusOnComplete($build, $context);
            } else {
                $repositoryService->setStatusOnStart($build, $context);
            }
        }
    }

    public static function createOrUpdateCheck($sha): void
    {
        // Find projectid from sha.
        // If this proves to be unreliable we could use the repositories table
        // instead.
        $db = Database::getInstance();
        $db->getPdo();
        $stmt = $db->prepare('
			SELECT projectid FROM build b
			JOIN buildupdate bu ON bu.id = b.updateid
			WHERE bu.revision = :sha
			LIMIT 1');
        $db->execute($stmt, ['sha' => $sha]);
        $projectid = $stmt->fetchColumn();
        if ($projectid === false) {
            return;
        }

        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();
        $repositoryInterface = self::getRepositoryInterface($project);
        $repositoryInterface->createCheck($sha);
    }

    public static function compareCommits(BuildUpdate $update, Project $project): void
    {
        $repositoryInterface = self::getRepositoryInterface($project);
        $repositoryInterface->compareCommits($update);
    }

    protected static function getRepositoryService(Project $project): ?RepositoryService
    {
        try {
            $repositoryInterface = self::getRepositoryInterface($project);
        } catch (Exception $e) {
            report($e);
            return null;
        }
        return new RepositoryService($repositoryInterface);
    }

    public static function getRepositoryInterface(Project $project): RepositoryInterface
    {
        switch (strtolower($project->CvsViewerType)) {
            case strtolower(self::VIEWER_GITHUB):
                $service = new GitHub($project);
                break;
            default:
                $msg =
                    "No repository interface defined for $project->CvsViewerType";
                throw new Exception($msg);
        }
        return $service;
    }
}
