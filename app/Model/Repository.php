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

use CDash\Lib\Repository\GitHub;
use CDash\Model\Build;
use CDash\Model\BuildProperties;
use CDash\Model\Project;
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
    const VIEWER_REDMINE = 'Redmine';
    const VIEWER_ALLURA = 'SourceForge Allura';
    const VIEWER_TRAC = 'Trac';
    const VIEWER_VIEWCVS = 'ViewCVS';
    const VIEWER_VIEWVC = 'ViewVC';
    const VIEWER_VIEWVC_1_1 = 'ViewVC1.1';
    const VIEWER_WEBSVN = 'WebSVN';

    /**
     * @return array
     * @throws \ReflectionException
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
