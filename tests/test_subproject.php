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

use CDash\Config;

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
        $url = Config::getInstance()->getBaseUrl();
        $expected = [
            'simpletest@localhost',
            'FAILED (w=21): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly',
            'A submission to CDash for the project SubProjectExample has build warnings.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06T08:19:56 EDT',
            'Type: Nightly',
            'Warnings: 21',
            '*Warnings* (first 5)',
            "packages/epetraext/src/transform/EpetraExt_BlockAdjacencyGraph.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/epetraext/src/transform/EpetraExt_BlockAdjacencyGrap',
            "packages/epetraext/src/block/EpetraExt_BlockDiagMatrix.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/epetraext/src/block/EpetraExt_BlockDiagMatrix.cpp: In member',
            "packages/epetraext/src/block/EpetraExt_MultiPointModelEvaluator.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/epetraext/src/block/EpetraExt_MultiPointModelEvalua',
            "packages/galeri/src/Galeri_Utils.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/galeri/src/Galeri_Utils.cpp: In function ‘void Galeri::Solve(const Epetra_RowMat',
            "packages/galeri/src/Galeri_CrsMatrices.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/galeri/src/CrsMatrices/Galeri_UniFlow2D.h: In function ‘Epetra_CrsMatrix*',
            '-CDash on',
            'function',
        ];
        if ($this->assertLogContains($expected, 32)) {
            $this->pass('Passed');
        }
    }

    public function testSubmissionSubProjectTest()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/SubProjectExample';
        $file = "$rep/Test_1.xml";
        if (!$this->submission('SubProjectExample', $file)) {
            return;
        }
        $url = Config::getInstance()->getBaseUrl();
        $expected = [
            'simpletest@localhost',
            'FAILED (t=1): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly',
            'A submission to CDash for the project SubProjectExample has failing tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06T08:19:56 EDT',
            'Type: Nightly',
            'Tests not passing: 1',
            '*Tests failing*',
            "NOX_FiniteDifferenceIsorropiaColoring | Completed (Failed) | ({$url}/testDetails.php?test=",
            '-CDash on cdash.dev',
            'function',
            'nox-noemail@noemail',
            'FAILED (t=1): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly',
            'A submission to CDash for the project SubProjectExample has failing tests.',
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            "Details on the submission can be found at {$url}/buildSummary.php?buildid=",
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06T08:19:56 EDT',
            'Type: Nightly',
            'Tests not passing: 1',
            '*Tests failing*',
            "NOX_FiniteDifferenceIsorropiaColoring | Completed (Failed) | ({$url}/testDetails.php?test=",
            '-CDash on cdash.dev',
            'function',
        ];
        if ($this->assertLogContains($expected, 45)) {
            $this->pass('Passed');
        }
    }
}
