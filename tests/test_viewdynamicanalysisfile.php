<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ViewDynamicAnalysisFileTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewDynamicAnalysisFile()
    {
        $this->get($this->url . '/viewDynamicAnalysisFile.php?id=1');
        if (strpos($this->getBrowser()->getContentAsText(), 'Dynamic analysis started on') === false) {
            $this->fail("'Dynamic analysis started on' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }

    public function testNextPrevious()
    {
        require_once('include/common.php');
        require_once('include/pdo.php');
        require_once('models/build.php');
        require_once('models/dynamicanalysis.php');

        // Submit testing data.
        $filenames = ['previous', 'next'];
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        foreach ($filenames as $filename) {
            $file = "$rep/$filename-DA.xml";
            if (!$this->submission('InsightExample', $file)) {
                $this->fail("Failed to submit $file");
            }
        }

        // Get id of existing build.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->query(
            "SELECT id FROM build
            WHERE name = 'Linux-g++-4.1-LesionSizingSandbox_Debug'");
        $row = $stmt->fetch();
        $buildid = $row['id'];
        if ($buildid < 1) {
            $this->fail('Could not find existing buildid');
        }

        // Get one of the dynamic analyses for this build.
        $stmt = $pdo->prepare(
            'SELECT id FROM dynamicanalysis WHERE buildid = ?');
        pdo_execute($stmt, [$buildid]);
        $row = $stmt->fetch();
        $id = $row['id'];
        if ($id < 1) {
            $this->fail('Could not find existing id');
        }

        // Test that previous/next/current function return meaningful values.
        $DA = new DynamicAnalysis();
        $DA->Id = $id;
        $DA->Fill();

        $build = new Build();
        $build->Id = $buildid;
        $build->FillFromId($build->Id);

        $previous_id = $DA->GetPreviousId($build);
        if ($previous_id < 1) {
            $this->fail('Could not find previous id');
        }
        $next_id = $DA->GetNextId($build);
        if ($next_id < 1) {
            $this->fail('Could not find next id');
        }
        $current_id = $DA->GetLastId($build);
        if ($current_id < 1) {
            $this->fail('Could not find current id');
        }

        // Delete builds created by this test case.
        $ids_to_delete = [$previous_id, $next_id];
        foreach ($ids_to_delete as $id_to_delete) {
            $stmt = $pdo->prepare(
                'SELECT buildid FROM dynamicanalysis WHERE id = ?');
            pdo_execute($stmt, [$id_to_delete]);
            $row = $stmt->fetch();
            $buildid = $row['buildid'];
            remove_build($buildid);
        }
    }
}
