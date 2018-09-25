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

        // Create two sites.
        // They have the same name but different ip addresses.
        $site3 = new Site();
        $site3->Name = 'testsite3';
        $site3->Ip = '';
        if (!$site3->Insert()) {
            $this->fail('Insert failed for site 3');
        }
        $site_3_id = $site3->Id;
        $site3->Id = null;
        if (!$site3->Exists()) {
            $this->fail('site 3 does not exist');
        }
        if ($site3->Id != $site_3_id) {
            $this->fail("Expected $site_3_id but found {$site3->Id} for site 3 ID");
        }

        $site4 = new Site();
        $site4->Name = 'testsite3';
        $site4->Ip = '127.0.0.1';
        if (!$site4->Insert()) {
            $this->fail('Insert failed for site 4');
        }
        $site_4_id = $site4->Id;
        $site4->Id = null;
        if (!$site4->Exists()) {
            $this->fail('site 4 does not exist');
        }
        if ($site4->Id != $site_4_id) {
            $this->fail("Expected $site_4_id but found {$site4->Id} for site 4 ID");
        }

        if ($site3->Id == $site4->Id) {
            $this->fail("Site 3 and Site 4 have the same Id");
        }

        $stmt = $this->PDO->prepare('DELETE FROM site WHERE id = ?');
        pdo_execute($stmt, [$site_3_id]);
        pdo_execute($stmt, [$site_4_id]);
        return 0;
    }
}
