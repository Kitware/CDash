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

class SubProjectTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function testAccessToWebPageProjectTest()
    {
        $this->login();
        // first project necessary for testing
        $name = 'SubProjectExample';
        $description = 'Project SubProjectExample test for cdash testing';

        // Create the project
        $this->clickLink('Create new project');
        $this->setField('name', $name);
        $this->setField('description', $description);
        $this->setField('public', '1');
        $this->setField('emailBrokenSubmission', '1');
        $this->setField('emailRedundantFailures', '1');
        $this->clickSubmitByName('Submit');

        $content = $this->connect($this->url . '/index.php?project=SubProjectExample');
        if (!$content) {
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
        $this->pass('Test passed');
    }

    public function testSubmissionProjectDependencies()
    {
        $rep = dirname(__FILE__) . "/data/SubProjectExample";
        $file = "$rep/Project_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            return;
        }
        $this->pass('Test passed');
    }

    public function testSubmissionSubProjectBuild()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . "/data/SubProjectExample";
        $file = "$rep/Build_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, $rep . "/cdash_1.log")) {
            return;
        }
        $this->pass('Test passed');
    }

    public function testSubmissionSubProjectTest()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . "/data/SubProjectExample";
        $file = "$rep/Test_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, $rep . "/cdash_2.log")) {
            return;
        }
        $this->pass('Test passed');
    }
}
