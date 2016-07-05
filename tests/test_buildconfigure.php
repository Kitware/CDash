<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/buildconfigure.php';
require_once 'models/buildconfigureerror.php';
require_once 'models/buildconfigureerrordiff.php';
require_once 'models/label.php';

class BuildConfigureTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildConfigure()
    {
        $this->startCodeCoverage();

        $configure = new BuildConfigure();
        $configure->BuildId = 'foo';
        ob_start();
        $configure->Exists();
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== 'BuildConfigure::Exists(): Buildid is not numeric') {
            $this->fail("'BuildId is not numeric' not found from Exists()");
            return 1;
        }

        $configure->BuildId = 1;
        $error = new BuildConfigureError();
        $error->ConfigureId = 1;
        $error->Type = 1;
        $configure->AddError($error);

        $diff = new BuildConfigureErrorDiff();
        $diff->BuildId = 1;
        $configure->AddErrorDifference($diff);

        $label = new Label();
        $configure->AddLabel($label);

        $configure->BuildId = false;
        ob_start();
        $configure->Exists();
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== 'BuildConfigure::Exists(): BuildId not set') {
            $this->fail("'BuildId not set' not found from Exists()");
            return 1;
        }
        $configure->BuildId = 1;
        $configure->Delete();

        $this->pass('Passed');

        $this->stopCodeCoverage();
        return 0;
    }
}
