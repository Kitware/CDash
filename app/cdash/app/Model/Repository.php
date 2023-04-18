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
use CDash\Lib\Repository\GitHub;
use CDash\Service\RepositoryService;

class Repository
{
    const CVS = 0;
    const SVN = 1;

    const VIEWER_CGIT = 'CGit';
    const VIEWER_CVSTRAC = 'CVSTrac';
    const VIEWER_FISHEYE = 'Fisheye';
    const VIEWER_GITHUB = 'GitHub';
    const VIEWER_GITLAB = 'GitLab';
    const VIEWER_GITORIOUS = 'Gitorious';
    const VIEWER_GITWEB = 'GitWeb';
    const VIEWER_GITWEB2 = 'GitWeb2';
    const VIEWER_HGWEB = 'Hgweb';
    const VIEWER_STASH = 'Atlassian Stash';
    const VIEWER_LOGGERHEAD = 'Loggerhead';
    const VIEWER_P4WEB = 'P4Web';
    const VIEWER_PHAB_GIT = 'Phabricator';
    const VIEWER_REDMINE = 'Redmine';
    const VIEWER_ALLURA = 'SourceForge Allura';
    const VIEWER_TRAC = 'Trac';
    const VIEWER_VIEWCVS = 'ViewCVS';
    const VIEWER_VIEWVC = 'ViewVC';
    const VIEWER_VIEWVC_1_1 = 'ViewVC1.1';
    const VIEWER_WEBSVN = 'WebSVN';

    /**
     * @return array
     */
    public static function getViewers()
    {
        $self = new \ReflectionClass(__CLASS__);
        $viewers = [];
        foreach ($self->getConstants() as $key => $text) {
            if (strpos($key, 'VIEWER_') === 0) {
                $value = strtolower(substr($key, strlen('VIEWER_')));
                $viewers[$text] = $value;
            }
        }
        return $viewers;
    }

    public static function setStatus(Build $build, $complete = true)
    {
        $buildProperties = new BuildProperties($build);
        $buildProperties->Fill();
        if (!array_key_exists('status context', $buildProperties->Properties)) {
            return;
        }
        $context = $buildProperties->Properties['status context'];

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

    public static function createOrUpdateCheck($sha)
    {
        // Find projectid from sha.
        // If this proves to be unreliable we could use the repositories table
        // instead.
        $db = Database::getInstance();
        $db->getPdo();
        $stmt = $db->prepare('
			SELECT projectid FROM build b
			JOIN build2update b2u ON b2u.buildid = b.id
			JOIN buildupdate bu ON bu.id = b2u.updateid
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

    public static function compareCommits(BuildUpdate $update, Project $project)
    {
        $repositoryInterface = self::getRepositoryInterface($project);
        $repositoryInterface->compareCommits($update);
    }

    protected static function getRepositoryService(Project $project)
    {
        try {
            $repositoryInterface = self::getRepositoryInterface($project);
        } catch (\Exception $e) {
            add_log($e->getMessage(), 'getRepositoryService', LOG_INFO);
            return null;
        }
        return new RepositoryService($repositoryInterface);
    }

    /**
     * @param Project $project
     * @return RepositoryInterface
     * @throws Exception
     */
    public static function getRepositoryInterface(Project $project)
    {
        switch (strtolower($project->CvsViewerType)) {
            case strtolower(self::VIEWER_GITHUB):
                $service = new GitHub($project);
                break;
            default:
                $msg =
                    "No repository interface defined for $project->CvsViewerType";
                throw new \Exception($msg);
                return;
        }
        return $service;
    }
}
