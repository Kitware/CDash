<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class BuildFailureDetailsTestCase extends KWWebTestCase
{
    protected $OriginalConfigSettings;

    public function __construct()
    {
        parent::__construct();
        $this->OriginalConfigSettings = '';
    }

    public function testBuildFailureDetails()
    {
        echo "1. testBuildFailureDetails\n";

        // Submit our test data.
        $rep = dirname(__FILE__) . '/data/BuildFailureDetails';
        if (!$this->submission('EmailProjectExample', "$rep/Build_1.xml")) {
            $this->fail('failed to submit Build_1.xml');
            return 1;
        }
        if (!$this->submission('EmailProjectExample', "$rep/Build_2.xml")) {
            $this->fail('failed to submit Build_2.xml');
            return 1;
        }

        // Get the buildids that we just created so we can delete them later.
        $buildids = [];
        $buildid_results = pdo_query(
            "SELECT id FROM build WHERE name='test_buildfailure'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }

        // Verify 4 buildfailures, 2 builds, and 2 details.
        $count_query = "
      SELECT COUNT(DISTINCT bf.id) AS numfails,
             COUNT(DISTINCT bf.buildid) AS numbuilds,
             COUNT(DISTINCT bf.detailsid) AS numdetails
      FROM buildfailure AS bf
      LEFT JOIN build AS b ON (b.id=bf.buildid)
      WHERE b.name='test_buildfailure'";
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numfails'] != 4) {
            $this->fail(
                'Expected 4 buildfailures, found ' . $count_results['numfails']);
            return 1;
        }
        if ($count_results['numbuilds'] != 2) {
            $this->fail(
                'Expected 2 builds, found ' . $count_results['numbuilds']);
            return 1;
        }
        if ($count_results['numdetails'] != 2) {
            $this->fail(
                'Expected 2 buildfailuredetails, found ' . $count_results['numdetails']);
            return 1;
        }

        // Delete one of the builds.
        remove_build($buildids[0]);

        // Verify 2 buildfailures, 1 build, and 2 details.
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numfails'] != 2) {
            $this->fail(
                'Expected 2 buildfailures, found ' . $count_results['numfails']);
            return 1;
        }
        if ($count_results['numbuilds'] != 1) {
            $this->fail(
                'Expected 1 build, found ' . $count_results['numbuilds']);
            return 1;
        }
        if ($count_results['numdetails'] != 2) {
            $this->fail(
                'Expected 2 buildfailuredetails, found ' . $count_results['numdetails']);
            return 1;
        }

        // Delete the other build.
        remove_build($buildids[1]);

        // Verify that the rest of our data is now gone.
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numfails'] != 0) {
            $this->fail(
                'Expected 0 buildfailures, found ' . $count_results['numfails']);
            return 1;
        }
        if ($count_results['numbuilds'] != 0) {
            $this->fail(
                'Expected 0 builds, found ' . $count_results['numbuilds']);
            return 1;
        }
        if ($count_results['numdetails'] != 0) {
            $this->fail(
                'Expected 0 buildfailuredetails, found ' . $count_results['numdetails']);
            return 1;
        }

        $this->pass('Test passed');
        return 0;
    }
}
