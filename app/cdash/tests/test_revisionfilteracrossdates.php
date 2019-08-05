<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class RevisionFilterIgnoresDateTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testRevisionFilterIgnoresDate()
    {
        // Verify that the revision filter can find builds that did not occur
        // during the current testing day.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&filtercount=1&showfilters=1&field1=revision&compare1=61&value1=91143198be7f9790c9b57de4051f134ce6070838');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        $expected = 3;
        $found = count($buildgroup['builds']);
        if ($found !== $expected) {
            $this->fail("Expected $expected builds, found $found");
        }
    }
}
