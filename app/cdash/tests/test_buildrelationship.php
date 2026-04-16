<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once __DIR__ . '/cdash_test_case.php';

use App\Utils\DatabaseCleanupUtils;
use App\Utils\SubmissionUtils;
use CDash\Database;
use CDash\Model\Build;

/**
 * This test is no longer used, but is kept for historical purposes because it generates data for
 * the expected-build Cypress tests.  This file should be removed when the expected-build Cypress
 * test is removed.
 */
class BuildRelationshipTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function testBuildRelationships(): void
    {
        // Clean up any previous runs of this test.
        $stmt = $this->PDO->prepare(
            "SELECT id FROM build WHERE name = 'test-build-relationships'");
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            DatabaseCleanupUtils::removeBuild($row['id']);
        }

        // Create three builds to relate to each other.
        $start_time = 1533838200;
        $projectid = get_project_id('InsightExample');
        $build1 = new Build();
        $build1->Name = 'test-build-relationships';
        $build1->SetStamp('20180809-1810-Experimental');
        $build1->StartTime = gmdate(FMT_DATETIME, $start_time);
        $build1->SiteId = 1;
        $build1->Type = 'Experimental';
        $build1->ProjectId = $projectid;
        $build2 = clone $build1;
        $build3 = clone $build1;

        $build1->Id = SubmissionUtils::add_build($build1);

        $build2->SetStamp('20180809-1811-Experimental');
        $build2->StartTime = gmdate(FMT_DATETIME, $start_time + 60);
        $build2->Id = SubmissionUtils::add_build($build2);

        $build3->SetStamp('20180809-1812-Experimental');
        $build3->StartTime = gmdate(FMT_DATETIME, $start_time + 120);
        $build3->Id = SubmissionUtils::add_build($build3);
    }
}
