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
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';
require_once 'models/user.php';

class UpdateOnlyUserStatsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->DataDir = dirname(__FILE__) . '/data/UpdateOnlyUserStats';
        $this->Project = null;
        $this->Users = array();
    }

    public function testSetup()
    {
        // Create a project named CDash.
        $this->login();
        $name = 'CDash';
        $description = 'CDash';
        $svnviewerurl = 'https://github.com/Kitware/CDash';
        $bugtrackerfileurl = 'http://public.kitware.com/Bug/view.php?id=';
        $this->createProject($name, $description, $svnviewerurl, $bugtrackerfileurl);
        $content = $this->connect($this->url . '/index.php?project=CDash');
        if (!$content) {
            return 1;
        }

        // Load details for this project.
        $projectid = get_project_id('CDash');
        $this->Project = new Project;
        $this->Project->Id = $projectid;
        $this->Project->Fill();

        // Mark this as a GitHub project.
        $this->Project->CvsViewerType = 'github';
        $this->Project->Save();

        // Add GitHub credentials for this repository so we don't get rate limited.
        global $configure;
        $this->Project->AddRepositories(
                array('https://github.com/Kitware/CDash'),
                array($configure['github_username']),
                array($configure['github_password']),
                array('master'));

        // Create some users for the CDash project.
        $users_details = array(
                array(
                    'email' => 'dan.lamanna@kitware.com',
                    'firstname' => 'Dan',
                    'lastname' => 'LaManna'),
                array(
                    'email' => 'jamie.snape@kitware.com',
                    'firstname' => 'Jamie',
                    'lastname' => 'Snape'),
                array(
                    'email' => 'zack.galbreath@kitware.com',
                    'firstname' => 'Zack',
                    'lastname' => 'Galbreath'));
        $userproject = new UserProject();
        $userproject->ProjectId = $projectid;
        foreach ($users_details as $user_details) {
            $user = new User();
            $user->Email = $user_details['email'];
            $user->FirstName = $user_details['firstname'];
            $user->LastName = $user_details['lastname'];
            $user->Password = md5('12345');
            $user->Institution = 'Kitware';
            $user->Admin = 0;
            $user->Save();
            $user->AddProject($userproject);
            $this->Users[] = $user;
        }
    }

    public function testUpdateOnlyUserStats()
    {
        // Submit testing data.
        $dirs = array('1', '2', '3');
        $files = array('Build.xml', 'Test.xml', 'Update.xml');
        foreach ($dirs as $dir) {
            foreach ($files as $file) {
                $file_to_submit = "$this->DataDir/$dir/$file";
                echo "submitting $file_to_submit\n";
                if (!$this->submission('CDash', $file_to_submit)) {
                    $this->fail("Failed to submit $file_to_submit");
                    return 1;
                }
            }
        }

        // Verify the results.
        $this->get($this->url . '/api/v1/userStatistics.php?project=CDash&date=2016-06-29&range=week');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        $numusers = count($jsonobj['users']);
        if ($numusers !== 3) {
            $this->fail("Expected stats for 3 users, found $numusers");
        }

        $expected_results = array(
                'Dan LaManna' => array(
                    'failed_errors' => 0,
                    'fixed_errors' => 0,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 1,
                    'failed_tests' => 0,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 2),
                'Jamie Snape' => array(
                    'failed_errors' => 0,
                    'fixed_errors' => 1,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 0,
                    'failed_tests' => 0,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 7),
                'Zack Galbreath' => array(
                    'failed_errors' => 0,
                    'fixed_errors' => 0,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 0,
                    'failed_tests' => 2,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 3));

        foreach ($jsonobj['users'] as $user) {
            $name = $user['name'];
            if (!array_key_exists($name, $expected_results)) {
                $this->fail("Unexpected user: $name");
                continue;
            }
            foreach ($expected_results[$name] as $key => $expected) {
                $found = $user[$key];
                if ($found !== $expected) {
                    $this->fail(
                        "Expected $expected but found $found for $name : $key");
                }
            }
        }
    }

    public function testCleanup()
    {
        // Delete users created by this test.
        foreach ($this->Users as $user) {
            $user->Delete();
        }

        // Delete builds.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                "SELECT id FROM build WHERE name='GithubUserStats'");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            remove_build($row['id']);
        }

        // Delete project.
        $this->Project->Delete();
    }
}
