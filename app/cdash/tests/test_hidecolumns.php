<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class HideColumnsTestCase extends KWWebTestCase
{
    protected $MethodsToTest;

    public function __construct()
    {
        parent::__construct();
        $this->MethodsToTest = ['Update', 'Configure', 'Build', 'Test'];
    }

    public function testHideColumns()
    {
        $success = true;

        foreach ($this->MethodsToTest as $method) {
            if (!$this->onlyColumn($method)) {
                $error_message = "onlyColumn test failed for $method\n";
                $success = false;
            }
        }

        if ($success) {
            $this->pass('Test passed');
            return 0;
        } else {
            $this->fail($error_message);
            return 1;
        }
    }

    public function onlyColumn($method)
    {
        // Submit our testing file.
        $rep = dirname(__FILE__) . '/data/HideColumns';
        if (!$this->submission('InsightExample', "$rep/$method.xml")) {
            return false;
        }

        // Use our API to verify which columns will be displayed.
        $content = $this->connect($this->url . '/api/v1/index.php?project=InsightExample&date=2015-10-06');
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $retval = false;
        switch ($method) {
            case 'Update':
                if ($buildgroup['hasupdatedata'] == true &&
                    $buildgroup['hasconfiguredata'] == false &&
                    $buildgroup['hascompilationdata'] == false &&
                    $buildgroup['hastestdata'] == false
                ) {
                    $retval = true;
                }
                break;
            case 'Configure':
                if ($buildgroup['hasupdatedata'] == false &&
                    $buildgroup['hasconfiguredata'] == true &&
                    $buildgroup['hascompilationdata'] == false &&
                    $buildgroup['hastestdata'] == false
                ) {
                    $retval = true;
                }
                break;
            case 'Build':
                if ($buildgroup['hasupdatedata'] == false &&
                    $buildgroup['hasconfiguredata'] == false &&
                    $buildgroup['hascompilationdata'] == true &&
                    $buildgroup['hastestdata'] == false
                ) {
                    $retval = true;
                }
                break;
            case 'Test':
                if ($buildgroup['hasupdatedata'] == false &&
                    $buildgroup['hasconfiguredata'] == false &&
                    $buildgroup['hascompilationdata'] == false &&
                    $buildgroup['hastestdata'] == true
                ) {
                    $retval = true;
                }
                break;
        }

        // Remove the build that we just created.
        $buildid_results = pdo_single_row_query(
            "SELECT id FROM build WHERE name='HideColumns'");
        $buildid = $buildid_results['id'];
        remove_build($buildid);
        return $retval;
    }
}
