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
use Exception;

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

    /**
     * @param Project $project
     * @return GitHub|null
     * @throws Exception
     */
    public static function factory(Project $project)
    {
        $service = null;

        switch (strtolower($project->CvsViewerType)) {
            case strtolower(self::VIEWER_GITHUB):
                list($owner, $repository) = array_values(
                    Repository::getGitHubRepoInformationFromUrl($project->CvsUrl)
                );

                $password = '';
                $repositories = $project->GetRepositories();
                foreach ($repositories as $repo) {
                    if (strpos($repo['url'], 'github.com') !== false) {
                        $password = $repo['password'];
                        break;
                    }
                }

                if (empty($password)) {
                    throw new Exception("Unable to find credentials for repository");
                }

                $service = new GitHub($repo['password'], $owner, $repository);
                break;
        }
        return $service;
    }

    /**
     * @param string $url
     * @return array
     */
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
