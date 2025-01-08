<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class CompressedTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSubmissionCompressedTest()
    {
        echo "1. testSubmissionCompressedTest\n";

        // Create project.
        $settings = [
            'Name' => 'TestCompressionExample',
            'Description' => 'Project compression example',
            'CvsUrl' => 'public.kitware.com/cgi-bin/viewcvs.cgi/?cvsroot=TestCompressionExample',
            'CvsViewerType' => 'github',
        ];
        $this->createProject($settings);

        // Make sure we can submit to it.
        $file = dirname(__FILE__) . '/data/CompressedTest.xml';
        $this->submission('TestCompressionExample', $file);
    }

    public function testGITUpdate()
    {
        echo "4. testGITUpdate\n";
        $file = dirname(__FILE__) . '/data/git-Update.xml';
        if (!$this->submission('TestCompressionExample', $file)) {
            return;
        }

        // Find the buildid that has the updates we just submitted.
        $this->get($this->url . '/api/v1/index.php?project=TestCompressionExample&date=2009-12-18');
        $response = json_decode($this->getBrowser()->getContentAsText(), true);
        $buildid = -1;
        foreach ($response['buildgroups'] as $buildgroup) {
            if ($buildgroup['name'] != 'Experimental') {
                continue;
            }
            foreach ($buildgroup['builds'] as $build) {
                if (!empty($build['update']['files'])) {
                    $buildid = $build['id'];
                    break;
                }
            }
        }
        if ($buildid == -1) {
            $this->fail('Could not find a build with update data');
            return;
        }

        // Verify that viewUpdate has the info we expect.
        $this->get($this->url . "/api/v1/viewUpdate.php?buildid=$buildid");
        $response = json_decode($this->getBrowser()->getContentAsText(), true);

        $expected = 'http://public.kitware.com/cgi-bin/viewcvs.cgi/?cvsroot=TestCompressionExample/compare/0758f1dbf75d1f0a1759b5f2d0aa00b3aba0d8c4...23a41258921e1cba8581ee2fa5add00f817f39fe';
        $found = $response['update']['revisionurl'];
        if (!str_contains($found, $expected)) {
            $this->fail("expected $expected but found $found for revisionurl");
            return;
        }

        $expected = 'http://public.kitware.com/cgi-bin/viewcvs.cgi/?cvsroot=TestCompressionExample/commit/0758f1dbf75d1f0a1759b5f2d0aa00b3aba0d8c4';
        $found = $response['update']['revisiondiff'];
        if (!str_contains($found, $expected)) {
            $this->fail("expected $expected but found $found for revisiondiff");
            return;
        }

        $this->pass('Test passed');
    }
}
