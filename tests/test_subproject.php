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
            'FAILED (w=21): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly' ,
            'A submission to CDash for the project SubProjectExample has warnings.',
            "Details on the submission can be found at {$url}/viewProject?projectid=",
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06 12:19:56',
            'Type: Nightly',
            'Total Warnings: 21',
            '*Warnings* (first 5 included)',
            "packages/epetraext/src/transform/EpetraExt_BlockAdjacencyGraph.cpp ({$url}/viewBuildError.php?type=",
            '/home/rabartl/PROJECTS/dashboards/Trilinos.base/SERIAL_RELEASE/Trilinos/packages/epetraext',
            "packages/epetraext/src/block/EpetraExt_BlockDiagMatrix.cpp ({$url}/viewBuildError.php?type=",
            'EpetraExt_BlockDiagMatrix.cpp: In member function ‘virtual void EpetraExt_BlockDiagMatrix::Print(std::ostream&) const',
            '/home/rabartl/PROJECTS/dashboards/',
            "packages/epetraext/src/block/EpetraExt_MultiPointModelEvaluator.cpp ({$url}/viewBuildError.php?type=",
            'EpetraExt_MultiPointModelEvaluator.h:',
            "packages/galeri/src/Galeri_Utils.cpp ({$url}/viewBuildError.php?type=",
            'In function ‘void Galeri::Solve',
            '/home/rabartl/PROJECTS/dashboard',
            "packages/galeri/src/Galeri_CrsMatrices.cpp ({$url}/viewBuildError.php?type=",
            'In function ‘Epetra_CrsMatrix* Galeri::Matrices::UniFlow2D',
            '/',
            '-CDash on cdash.dev',
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
            'nox-noemail@noemail',
            'FAILED (t=1): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly',
            'A submission to CDash for the project SubProjectExample has failing tests',
            "Details on the submission can be found at {$url}/viewProject?projectid=",
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06 12:19:56',
            'Type: Nightly',
            'Total Failing Tests: 1',
            '*Failing Tests*',
            "NOX_FiniteDifferenceIsorropiaColoring | Completed (Failed) | ({$url}/testDetails.php?test=",
            '-CDash on cdash.dev',
            'simpletest@localhost',
            'FAILED (t=1): SubProjectExample/NOX - Linux-GCC-4.1.2-SERIAL_RELEASE - Nightly',
            'A submission to CDash for the project SubProjectExample has failing tests',
            'Details on the submission can be found at http://cdash.dev/viewProject?projectid=',
            'Project: SubProjectExample',
            'SubProject: NOX',
            'Site: godel.sandia.gov',
            'Build Name: Linux-GCC-4.1.2-SERIAL_RELEASE',
            'Build Time: 2009-08-06 12:19:56',
            'Type: Nightly',
            'Total Failing Tests: 1',
            '*Failing Tests*',
            "NOX_FiniteDifferenceIsorropiaColoring | Completed (Failed) | ({$url}/testDetails.php?test=",
            '-CDash on cdash.dev',
        ];
        if ($this->assertLogContains($expected, 39)) {
            $this->pass('Passed');
        }
    }
}
