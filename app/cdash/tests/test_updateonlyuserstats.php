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



use App\Models\User;
use CDash\Model\UserProject;

class UpdateOnlyUserStatsTestCase extends KWWebTestCase
{
    protected $DataDir;
    protected $ProjectId;
    protected $Users;

    public function __construct()
    {
        parent::__construct();
        $this->DataDir = dirname(__FILE__) . '/data/UpdateOnlyUserStats';
        $this->ProjectId = null;
        $this->Users = [];
    }

    public function testSetup()
    {
        // Create a project with GitHub credentials named CDash.
        $settings = [
            'Name' => 'CDash',
            'Description' => 'CDash',
            'CvsUrl' => 'github.com/Kitware/CDash',
            'CvsViewerType' => 'github',
            'BugTrackerFileUrl' => 'http://public.kitware.com/Bug/view.php?id=',
            'repositories' => [[
                'url' => 'https://github.com/Kitware/CDash',
                'branch' => 'master',
                'username' => '',
                'password' => '',
            ]],
        ];
        $this->ProjectId = $this->createProject($settings);

        // Create some users for the CDash project.
        $users_details = [
                [
                    'email' => 'dan.lamanna@kitware.com',
                    'firstname' => 'Dan',
                    'lastname' => 'LaManna'],
                [
                    'email' => 'jamie.snape@kitware.com',
                    'firstname' => 'Jamie',
                    'lastname' => 'Snape'],
                [
                    'email' => 'zack.galbreath@kitware.com',
                    'firstname' => 'Zack',
                    'lastname' => 'Galbreath']];
        $userproject = new UserProject();
        $userproject->ProjectId = $this->ProjectId;
        foreach ($users_details as $user_details) {
            $user = new User();
            $user->email = $user_details['email'];
            $user->firstname = $user_details['firstname'];
            $user->lastname = $user_details['lastname'];
            $user->password = password_hash('12345', PASSWORD_DEFAULT);
            $user->institution = 'Kitware';
            $user->admin = 0;
            $user->Save();
            $userproject->UserId = $user->id;
            $userproject->Save();
            $this->Users[] = $user;
        }
    }

    public function testUpdateOnlyUserStats()
    {
        // Submit testing data.
        $dirs = ['1', '2', '3'];
        $files = ['Build.xml', 'Test.xml', 'Update.xml'];
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

        $expected_results = [
                'Dan LaManna' => [
                    'failed_errors' => 0,
                    'fixed_errors' => 0,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 1,
                    'failed_tests' => 0,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 2],
                'Jamie Snape' => [
                    'failed_errors' => 0,
                    'fixed_errors' => 1,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 0,
                    'failed_tests' => 0,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 7],
                'Zack Galbreath' => [
                    'failed_errors' => 0,
                    'fixed_errors' => 0,
                    'failed_warnings' => 0,
                    'fixed_warnings' => 0,
                    'failed_tests' => 2,
                    'fixed_tests' => 0,
                    'totalupdatedfiles' => 3]];

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
        $this->deleteProject($this->ProjectId);
    }
}
