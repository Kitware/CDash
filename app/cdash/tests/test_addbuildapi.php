<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Build;

class AddBuildAPITestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildRelationships()
    {
        // Login as admin.
        $client = $this->getGuzzleClient();

        // Check for expected error messages when missing parameters.
        // No project.
        $response = $client->request('POST',
            $this->url .  '/api/v1/addBuild.php',
            ['http_errors' => false]);
        $response = json_decode($response->getBody());
        $this->assertTrue(property_exists($response, 'error'));
        $this->assertEqual($response->error, 'Valid project required');

        // No site.
        $response = $client->request('POST',
            $this->url .  '/api/v1/addBuild.php?project=InsightExample',
            ['http_errors' => false]);
        $response = json_decode($response->getBody());
        $this->assertTrue(property_exists($response, 'error'));
        $this->assertEqual($response->error, 'Valid site required');

        // No build name.
        $response = $client->request('POST',
            $this->url .  '/api/v1/addBuild.php?project=InsightExample&site=HOME',
            ['http_errors' => false]);
        $response = json_decode($response->getBody());
        $this->assertTrue(property_exists($response, 'error'));
        $this->assertEqual($response->error, 'Valid name required');

        $response = $client->request('POST',
            $this->url .  '/api/v1/addBuild.php?project=InsightExample&site=HOME&name=testaddbuildapi',
            ['http_errors' => false]);
        $response = json_decode($response->getBody());
        $this->assertTrue(property_exists($response, 'error'));
        $this->assertEqual($response->error, 'Valid stamp required');

        // API actually adds a build.
        $response = $client->request('POST',
            $this->url .  '/api/v1/addBuild.php?project=InsightExample&site=HOME&name=testaddbuildapi&stamp=20180705-0100-Experimental',
            ['http_errors' => false]);
        $response = json_decode($response->getBody());
        $this->assertFalse(property_exists($response, 'error'));
        $this->assertTrue(property_exists($response, 'buildid'));
        $this->assertTrue($response->buildid > 0);

        $build = new Build();
        $build->Id = $response->buildid;
        $this->assertTrue($build->Exists());

        // Cleanup.
        remove_build($build->Id);
    }
}
