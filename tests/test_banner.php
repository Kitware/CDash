<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/banner.php';

class BannerTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBanner()
    {
        $banner = new Banner();

        ob_start();
        $result = $banner->SetText('banner');
        $output = ob_get_contents();
        ob_end_clean();
        if ($result) {
            $this->fail('SetText() should return false when ProjectId is -1');
            return 1;
        }
        if (strpos($output, 'Banner::SetText(): no ProjectId specified') === false) {
            $this->fail("'no ProjectId specified' not found from SetText()");
            return 1;
        }

        //set a reasonable project id
        $banner->SetProjectId(1);

        //test insert
        $banner->SetText('banner');

        //test update
        $banner->SetText('banner');

        if ($banner->GetText() != 'banner') {
            $this->fail("GetText() should have returned 'banner'.");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
