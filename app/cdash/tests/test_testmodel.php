<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Image;
use CDash\Model\Test;
use CDash\Model\TestMeasurement;

class TestModelTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestModel()
    {
        $test = new Test();
        $test->Id = '8967';
        $test->Name = 'dummytest';
        $test->ProjectId = 2;

        // Cover error condition
        $test->InsertLabelAssociations('');

        $testmeasurement = new TestMeasurement();
        $testmeasurement->Name = 'Label';
        $testmeasurement->Value = 'Some_Label';
        $test->AddMeasurement($testmeasurement);

        $image = new Image();
        $image->Filename = dirname(__FILE__) . '/data/smile.gif';
        $image->Data = base64_encode(file_get_contents($image->Filename));
        $image->Checksum = 100;
        $image->Extension = 'image/gif';

        $test->AddImage($image);

        $test->Insert();

        $test->GetCrc32();
        return 0;
    }
}
