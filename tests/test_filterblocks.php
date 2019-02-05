<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

class FilterBlocksTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testFilterBlocks()
    {
        // Verify that a set of filters containing a sub-block returns
        // the two builds that we expect.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=2&showfilters=1&filtercombine=and&field1=revision&compare1=63&value1=911431&field2=block&field2count=2&field2field1=configureerrors&field2compare1=43&field2value1=0&field2field2=testsfailed&field2compare2=43&field2value2=19');
        $this->verifyTwoHutBuilds();
    }

    public function testSubProjectFilterWorksWithOr()
    {
        // Verify that the server-side filtering logic for SubProjects works
        // with filters combined by the 'or' operator.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=or&field1=subprojects&compare1=92&value1=Teuchos&field2=testsfailed&compare2=43&value2=19&field3=builderrors&compare3=43&value3=4');
        $this->verifyTwoHutBuilds();
    }

    public function testSubProjectFilterWorksWithBlocks()
    {
        // Verify that the SubProject filter can peacefully coexist with
        // a filter sub-block.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=2&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=Teuchos&field2=block&field2count=2&field2field1=builderrors&field2compare1=41&field2value1=5&field2field2=configurewarnings&field2compare2=41&field2value2=3');
        $this->verifyTwoHutBuilds();
    }

    public function testFilterBlocksRemoveDefaultDateField()
    {
        // Verify that filters which modify the default date search range
        // (such as 'Build Start Time') can be used within a block.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&filtercount=2&showfilters=1&filtercombine=or&field1=block&field1count=2&field1field1=site&field1compare1=61&field1value1=hut11.kitware&field1field2=buildstarttime&field1compare2=84&field1value2=yesterday&field2=block&field2count=2&field2field1=site&field2compare1=61&field2value1=hut12.kitware&field2field2=buildstarttime&field2compare2=84&field2value2=yesterday');
        $this->verifyTwoHutBuilds();
    }

    public function testFiltersAreDisplayedEvenWhenTheirSQLIsPruned()
    {
        // Verify that fields that are affected by the SubProject filters are
        // displayed in sub-blocks when we are also filtering on SubProjects.
        $this->get($this->url . '/api/v1/filterdata.php?compare1=92&date=2011-07-22&field1=subprojects&field2=block&field2compare1=43&field2compare2=43&field2count=2&field2field1=builderrors&field2field2=configurewarnings&field2value1=5&field2value2=3&filtercombine=and&filtercount=2&page_id=index.php&project=Trilinos&showfilters=1&showlimit=0&value1=Mesquite');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $filters = $jsonobj['filters'];

        $expected = json_decode('[{"field":"subprojects","compare":92,"value":"Mesquite"},{"filters":[{"field":"builderrors","compare":43,"value":5},{"field":"configurewarnings","compare":43,"value":3}]}]', true);
        $this->assertTrue($filters === $expected);
    }

    public function testFilterBlocksAreRemovedWhenNoSubFiltersRemain()
    {
        // Filter out some SubProjects and add a filter block containing
        // elements that aren't preserved in the child build hyperlink.
        $this->get($this->url . '/api/v1/index.php?project=Trilinos&date=2011-07-22&filtercount=3&showfilters=1&filtercombine=and&field1=subprojects&compare1=92&value1=Teuchos&field2=subprojects&compare2=92&value2=Mesquite&field3=block&field3count=2&field3field1=site&field3compare1=61&field3value1=hut11.kitware&field3field2=buildname&field3compare2=61&field3value2=Windows_NT-MSVC10-SERIAL_DEBUG_DEV');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);

        // Verify one build.
        $num_builds = count($buildgroup['builds']);
        if ($num_builds != 1) {
            $this->fail("Expected 1 build, found $num_builds");
        }

        // Verify the link to the child build is what we expect (no filter block).
        $build = $buildgroup['builds'][0];
        $expected = 'filtercount=2&showfilters=1&field1=subproject&compare1=62&value1=Teuchos&field2=subproject&compare2=62&value2=Mesquite&filtercombine=and';
        $this->assertTrue(strpos($build['multiplebuildshyperlink'], $expected) !== false);
    }

    private function verifyTwoHutBuilds()
    {
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $num_builds = count($buildgroup['builds']);
        if ($num_builds != 2) {
            $this->fail("Expected 2 builds, found $num_builds");
        }
        foreach ($buildgroup['builds'] as $build) {
            $sitename = $build['site'];
            if (strpos($sitename, 'hut') === false) {
                $this->fail("Expected sitename to contain 'hut', instead got $sitename");
            }
        }
    }
}
