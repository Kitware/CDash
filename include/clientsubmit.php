<?php
/*=========================================================================
  Clientgram:   CDash - Cross-Platform Dashboard System
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

use CDash\Config;
use CDash\Model\ClientSite;
use CDash\Model\ClientOS;
use CDash\Model\ClientJobSchedule;
use CDash\Model\ClientCompiler;
use CDash\Model\ClientCMake;
use CDash\Model\ClientLibrary;
use CDash\Model\ClientJob;

function client_submit()
{
    $config = Config::getInstance();
    if (!$config->get('CDASH_MANAGE_CLIENTS')) {
        return 0;
    }

    include 'config/config.php';
    require_once 'include/common.php';

    // Client asks for the site id
    if (isset($_GET['getsiteid'])) {
        if (!isset($_GET['sitename']) || !isset($_GET['systemname'])) {
            echo 'ERROR: sitename or systemname not set';
            return 0;
        }

        $sitename = htmlspecialchars(pdo_real_escape_string($_GET['sitename']));
        $systemname = htmlspecialchars(pdo_real_escape_string($_GET['systemname']));

        // Should get the site id
        $ClientSite = new ClientSite();
        $siteid = $ClientSite->GetId($sitename, $systemname);

        echo $siteid;
        return 1;
    } // If the client asks for something to build
    elseif (isset($_GET['getjob'])) {
        if (!isset($_GET['siteid'])) {
            echo '0';
            return 1;
        }
        if (!$_GET['siteid']) {
            echo '0';
            return 1;
        }

        $ClientJobSchedule = new ClientJobSchedule();
        $ClientJobSchedule->SiteId = pdo_real_escape_numeric($_GET['siteid']);

        $jobid = $ClientJobSchedule->HasJob();
        if ($jobid > 0) {
            // if we have something to do

            echo $ClientJobSchedule->GetCTestScript();
        } else {
            echo '0'; // send zero to let the client know that nothing is there
        }
        return 1;
    } elseif (isset($_GET['submitinfo'])) {
        if (!isset($_GET['sitename']) || !isset($_GET['systemname'])) {
            echo '0';
            return 1;
        }

        $filehandle = 'php://input';
        $contents = file_get_contents($filehandle);
        $xml = new SimpleXMLElement($contents);

        // Add/Update the OS
        $ClientOS = new ClientOS();
        $ClientOS->Name = $ClientOS->GetPlatformFromName($xml->system->platform);
        $ClientOS->Version = $ClientOS->GetVersionFromName($xml->system->version);
        $ClientOS->Bits = $xml->system->bits;
        $ClientOS->Save();

        // Add/Update the site
        $ClientSite = new ClientSite();
        $ClientSite->Name = htmlspecialchars(pdo_real_escape_string($_GET['sitename']));
        $ClientSite->SystemName = htmlspecialchars(pdo_real_escape_string($_GET['systemname']));
        $ClientSite->Host = 'none';
        $ClientSite->OsId = $ClientOS->Id;
        $ClientSite->BaseDirectory = $xml->system->basedirectory;
        $ClientSite->Save();

        $siteid = $ClientSite->Id;

        // Add/Update the compiler(s)
        $compilers = array();
        foreach ($xml->compiler as $compiler) {
            $ClientCompiler = new ClientCompiler();
            $ClientCompiler->Name = $compiler->name;
            $ClientCompiler->Version = $compiler->version;
            $ClientCompiler->Command = $compiler->command;
            $ClientCompiler->Generator = $compiler->generator;
            $ClientCompiler->SiteId = $siteid;
            $ClientCompiler->Save();

            $comp = array();
            $comp['name'] = $compiler->name;
            $comp['version'] = $compiler->version;
            $comp['command'] = $compiler->command;
            $comp['generator'] = $compiler->generator;
            $compilers[] = $comp;
        }
        $ClientCompiler = new ClientCompiler();
        $ClientCompiler->SiteId = $siteid;
        $ClientCompiler->DeleteUnused($compilers);

        // Add/Update CMake(s)
        $cmakes = array();
        foreach ($xml->cmake as $cmake) {
            $ClientCMake = new ClientCMake();
            $ClientCMake->Version = $cmake->version;
            $ClientCMake->Path = $cmake->path;
            $ClientCMake->SiteId = $siteid;
            $ClientCMake->Save();

            $cm = array();
            $cm['path'] = $cmake->path;
            $cm['version'] = $cmake->version;
            $cmakes[] = $cm;
        }
        $ClientCMake = new ClientCMake();
        $ClientCMake->SiteId = $siteid;
        $ClientCMake->DeleteUnused($cmakes);

        // Add/Update Libraries
        $libraries = array();
        foreach ($xml->library as $library) {
            $ClientLibrary = new ClientLibrary();
            $ClientLibrary->Name = $library->name;
            $ClientLibrary->Path = $library->path;
            $ClientLibrary->Include = $library->include;
            $ClientLibrary->Version = $library->version;
            $ClientLibrary->SiteId = $siteid;
            $ClientLibrary->Save();

            $lib = array();
            $lib['name'] = $library->name;
            $lib['path'] = $library->path;
            $lib['version'] = $library->version;
            $lib['include'] = $library->include;
            $libraries[] = $lib;
        }
        $ClientLibrary = new ClientLibrary();
        $ClientLibrary->SiteId = $siteid;
        $ClientLibrary->DeleteUnused($libraries);

        // Add/Update Programs
        $programs = array();
        foreach ($xml->program as $program) {
            $prog = array();
            $prog['name'] = $program->name;
            $prog['path'] = $program->path;
            $prog['version'] = $program->version;
            $programs[] = $prog;
        }
        $ClientSite->UpdatePrograms($programs);

        // Add/Update the list of allowed projects
        $allowedProjects = array();
        foreach ($xml->allowedproject as $allowedProject) {
            $allowedProjects[] = $allowedProject;
        }
        $ClientSite->UpdateAllowedProjects($allowedProjects);
        return 1;
    } elseif (isset($_GET['jobdone'])) {
        // Mark the job has finished

        if (!isset($_GET['siteid'])) {
            echo '0';
            return 1;
        }
        if (!$_GET['siteid']) {
            echo '0';
            return 1;
        }
        $ClientJob = new ClientJob();
        $ClientJob->SiteId = pdo_real_escape_numeric($_GET['siteid']);
        $ClientJob->SetFinished();
        return 1;
    } elseif (isset($_GET['jobfailed'])) {
        // Mark the job has failed

        if (!isset($_GET['siteid'])) {
            echo '0';
            return 1;
        }
        if (!$_GET['siteid']) {
            echo '0';
            return 1;
        }
        $ClientJob = new ClientJob();
        $ClientJob->SiteId = pdo_real_escape_numeric($_GET['siteid']);
        $ClientJob->SetFailed();
        return 1;
    }
    return 0;
}
