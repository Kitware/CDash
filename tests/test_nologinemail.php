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

class NoLoginEmailTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function submitProjectFile()
    {
        $file = dirname(__FILE__) . '/data/NoLoginEmail/Project.xml';
        if (!$this->submission('NoLoginEmail', $file)) {
            $this->fail('Could not submit project file');
            return false;
        }
        return true;
    }

    public function setUp()
    {
        parent::setUp();
        $settings = array(
                'Name' => 'NoLoginEmail',
                'Description' => 'Test for no-login emails created in project file');
        $this->createProject($settings);
    }

    public function tearDown()
    {
        parent::tearDown();
        $projectid = get_project_id('NoLoginEmail');
        $this->deleteProject($projectid);
        $this->removeLineFromConfig('$CDASH_NO_REGISTRATION = 1;');
        $user = new User();
        $user->Id = $user->GetIdFromEmail('sampleuser@noemail');
        $user->Delete();
    }

    public function testLoginEmail()
    {
        $this->removeLineFromConfig('$CDASH_NO_REGISTRATION = 1;');

        if (!$this->submitProjectFile()) {
            return;
        }

        $content = $this->login('sampleuser@noemail', 'sampleuser@noemail');
        if (strpos($content, 'Wrong email or password') !== false) {
            $this->fail('Failed to log in');
            return;
        }

        $this->assertTrue(true, 'All tests passed');
    }

    public function testNoLoginEmail()
    {
        $this->addLineToConfig('$CDASH_NO_REGISTRATION = 1;');

        if (!$this->submitProjectFile()) {
            return;
        }

        $content = $this->login('sampleuser@noemail', 'sampleuser@noemail');
        if (strpos($content, 'Wrong email or password') === false) {
            $this->fail('Successfully logged in');
            return;
        }

        $this->assertTrue(true, 'All tests passed');
    }
}
