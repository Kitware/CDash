<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Image;
use CDash\Model\TestImage;

class ImageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testImage()
    {
        $image = new Image();

        //no id, no matching checksum
        $image->Id = 0;
        if ($image->Exists()) {
            $this->fail('Exists() should return false when Id is 0');
            return 1;
        }

        //id, no matching checksum
        $image->Id = 1;
        if ($image->Exists()) {
            $this->fail("Exists() should return false with no matching checksum\n");
        }

        $pathToImage = dirname(__FILE__) . '/data/smile.gif';
        $image->Filename = $pathToImage;
        $image->Extension = 'image/gif';
        //dummy checksum so we don't break the test on pgSQL
        $image->Checksum = 100;

        //call save twice to cover different execution paths
        if (!$image->Save()) {
            $this->fail("Save() call #1 returned false when it should be true.\n");
            return 1;
        }
        if (!$image->Save()) {
            $this->fail("Save() call #2 returned false when it should be true.\n");
            return 1;
        }

        //exercise the TestImage class as well
        $testimage = new TestImage();

        $testimage->Id = 1;
        $testimage->TestId = 1;

        if ($testimage->Exists()) {
            $this->fail("testimage shouldn't exist yet.\n");
            return 1;
        }

        if (!$testimage->Insert()) {
            $this->fail("testimage::Insert() shouldn't have returned false.\n");
            return 1;
        }

        if (!$testimage->Exists()) {
            $this->fail("testimage should exist at this point.\n");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
