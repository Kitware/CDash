<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

class QueryTestsFilterLabelsTestCase extends KWWebTestCase
{

    // Note: this test reuses existing data from 'EmailProjectExample'.
    public function testQueryTestsFilterLabels()
    {
        // Baseline (no filters).
        $this->get("{$this->url}/api/v1/queryTests.php?project=EmailProjectExample&date=2009-02-23");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(27, count($jsonobj['builds']));

        // Test 'Revision' 'is' filter.
        $this->get("{$this->url}/api/v1/queryTests.php?project=EmailProjectExample&date=2009-02-23&filtercount=1&showfilters=1&field1=revision&compare1=61&value1=23a41258921e1cba8581ee2fa5add00f817f39fe");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(8, count($jsonobj['builds']));

        // Test 'Revision' 'is not' filter.
        $this->get("{$this->url}/api/v1/queryTests.php?project=EmailProjectExample&date=2009-02-23&filtercount=1&showfilters=1&field1=revision&compare1=62&value1=23a41258921e1cba8581ee2fa5add00f817f39fe");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(19, count($jsonobj['builds']));
    }
}
