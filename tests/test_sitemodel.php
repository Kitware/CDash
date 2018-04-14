<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Site;

class SiteModelTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->site = null;
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function __destruct()
    {
        $this->PDO->query("DELETE FROM site WHERE id = " . $this->site->Id);
    }

    public function testSiteModel()
    {
        $this->site = new Site();

        if ($this->site->Exists() !== false) {
            $this->fail('Exists did not return false for unnamed site');
            return 1;
        }

        if ($this->site->Update() !== false) {
            $this->fail('Update did not return false for unnamed site');
            return 1;
        }

        if ($this->site->Insert() !== false) {
            $this->fail('Insert did not return false for unnamed site');
            return 1;
        }

        if ($this->site->GetName() !== false) {
            $this->fail('GetName did not return false for unnamed site');
            return 1;
        }

        $this->site->Name = 'testsite';

        if ($this->site->Exists() !== false) {
            $this->fail('Exists did not return false for nonexistent site');
            return 1;
        }

        if ($this->site->Update() !== false) {
            $this->fail('Update did not return false for nonexistent site');
            return 1;
        }

        if (!$this->site->Insert()) {
            $this->fail('Insert returned false for named site');
            return 1;
        }

        if ($this->site->GetName() !== 'testsite') {
            $this->fail('GetName did not return expected value after Insert');
            return 1;
        }

        $this->site->Name = 'testsite2';
        if (!$this->site->Update()) {
            $this->fail('Update returned false for named site');
            return 1;
        }

        if ($this->site->GetName() !== 'testsite2') {
            $this->fail('GetName did not return expected value after Update');
            return 1;
        }

        return 0;
    }
}
