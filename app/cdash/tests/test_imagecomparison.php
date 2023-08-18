<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



use CDash\Model\Image;

class ImageComparisonTestCase extends KWWebTestCase
{
    protected $BuildId;

    public function __construct()
    {
        parent::__construct();
        $this->BuildId = null;
    }

    public function testImageComparison()
    {
        // Submit test data.
        if (!$this->submission('InsightExample', dirname(__FILE__) . '/data/ImageComparisonTest.xml')) {
            $this->fail('Failed to submit test data');
            return 1;
        }

        // Get the images created by this test.
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->query(
            "SELECT b.id AS buildid, i.id AS imgid FROM build b
            JOIN build2test b2t ON (b2t.buildid=b.id)
            JOIN test2image t2i ON (b2t.outputid=t2i.outputid)
            JOIN image i ON (i.id=t2i.imgid)
            WHERE b.name='image_comparison'");
        $imgids = [];
        $buildid = 0;
        while ($row = $stmt->fetch()) {
            $buildid = $row['buildid'];
            $imgids[] = $row['imgid'];
        }
        $num_imgs = count($imgids);
        if ($num_imgs !== 3) {
            $this->fail("Expected 3 images, found $num_imgs");
        }

        foreach ($imgids as $imgid) {
            $image = new Image();
            $image->Id = $imgid;
            $image->Load();
            if (imagecreatefromstring($image->Data) === false) {
                $this->fail("Image $imgid is corrupted");
            }
        }

        remove_build($buildid);
    }
}
