<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class GroupsDescriptionTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testGroupsDescription()
    {
        $this->login();
        $this->get($this->url . '/groupsDescription.php?project=SubProjectExample');
        if (strpos($this->getBrowser()->getContentAsText(), 'Continuous builds') === false) {
            $this->fail("'Continuous builds' not found when expected.");
            return 1;
        }
        $this->pass('Passed');
    }
}
