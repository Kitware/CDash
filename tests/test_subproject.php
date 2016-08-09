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
        $settings = array(
                'Name' => 'SubProjectExample',
                'Description' => 'Project SubProjectExample test for cdash testing',
                'EmailBrokenSubmission' => 1,
                'EmailRedundantFailures' => 1);
        $this->createProject($settings);
    }

    public function testSubmissionProjectDependencies()
    {
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
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
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
        $file = "$rep/Build_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, $rep . '/cdash_1.log')) {
            return;
        }
        $this->pass('Test passed');
    }

    public function testSubmissionSubProjectTest()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
        $file = "$rep/Test_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        if (!$this->compareLog($this->logfilename, $rep . '/cdash_2.log')) {
            return;
        }
        $this->pass('Test passed');
    }
}
