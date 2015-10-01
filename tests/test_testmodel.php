<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/common.php');
require_once('cdash/pdo.php');
require_once('models/image.php');
require_once('models/test.php');
require_once('models/testmeasurement.php');

class TestModelTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestModel()
    {
        $this->startCodeCoverage();

        $test = new Test();
        $test->Id = "8967";
        $test->Name = "dummytest";
        $test->ProjectId = 2;

    // Cover error condition
    $test->InsertLabelAssociations('');

        $testmeasurement = new TestMeasurement();
        $testmeasurement->Name = "Label";
        $testmeasurement->Value = "Some_Label";
        $test->AddMeasurement($testmeasurement);

        $image = new Image();
        $image->Filename = dirname(__FILE__)."/data/smile.gif";
        $image->Data = base64_encode(file_get_contents($image->Filename));
        $image->Checksum = 100;
        $image->Extension = "image/gif";

        $test->AddImage($image);

        $test->Insert();

        $test->GetCrc32();

        $this->stopCodeCoverage();

        return 0;
    }
}
