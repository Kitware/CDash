<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Banner;

class BannerTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBanner()
    {
        $banner = new Banner();

        $result = $banner->SetText('banner');
        if ($result) {
            $this->fail('SetText() should return false when ProjectId is -1');
            return 1;
        }

        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'No ProjectId specified') === false) {
            $this->fail("'No ProjectId specified' not found from SetText()");
            return 1;
        }

        //set a reasonable project id
        $banner->SetProjectId(1);

        //test insert
        if (!$banner->SetText('banner')) {
            $this->fail('SetText #1 returned false');
        }

        //test update
        if (!$banner->SetText('banner')) {
            $this->fail('SetText #2 returned false');
        }

        if ($banner->GetText() != 'banner') {
            $this->fail("GetText() should have returned 'banner'.");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
