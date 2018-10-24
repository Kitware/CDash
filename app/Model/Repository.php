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
use CDash\Lib\Repository\GitHub;
use CDash\Log;

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

    public static function factory(Project $project)
    {
        switch ($project->CvsViewerType)
        {
            case self::VIEWER_GITHUB:
                list($owner, $repo) = array_values(
                    Repository::getGitHubRepoInformationFromUrl($project->CvsUrl)
                );
                $token = Config::getInstance()->get('CDASH_GITHUB_API_TOKEN');
                $service = new GitHub($token, $owner, $repo);
                break;
            default:
                $vcs = $project->CvsViewerType ?: 'none';
                $e = new \Exception("Unknown CvsViewerType [{$vcs}]");
                Log::getInstance()->error($e);
        }
        return $service;
    }

    protected static function getGitHubRepoInformationFromUrl($url)
    {
        $url = str_replace('//', '', $url);
        $parts = explode('/', $url);
        $info = ['owner' => null, 'repo' => null];
        if (isset($parts[1])) {
            $info['owner'] = $parts[1];
        }

        if (isset($parts[2])) {
            $info['repo'] = $parts[2];
        }

        return $info;
    }
}
