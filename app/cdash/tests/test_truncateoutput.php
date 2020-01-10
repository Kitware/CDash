<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__).'/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class TruncateOutputTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigLine = "\$CDASH_LARGE_TEXT_LIMIT = '44';\n";
        $this->Expected = "The beginning survives\n...\nCDash truncated output because it exceeded 44 characters.\n...\nThis part is preserved\n";
        $this->BuildId = 0;
    }

    public function testTruncateOutput()
    {
        // Set a limit so long output will be truncated.
        $this->addLineToConfig($this->ConfigLine);
        $rep  = dirname(__FILE__)."/data/TruncateOutput";

        foreach (['Build_stdout.xml', 'Build_stderr.xml', 'Build_both.xml'] as $file) {
            // Submit our testing data.
            if (!$this->submission('InsightExample', "$rep/$file")) {
                $this->fail("failed to submit $file");
                $this->cleanup();
                return 1;
            }

            // Query for the ID of the build that we just created.
            $buildid_results = pdo_single_row_query(
                    "SELECT id FROM build WHERE name='TruncateOutput'");
            $this->BuildId = $buildid_results['id'];

            // Verify that the output was properly truncated.
            $fields = [];
            if ($file == 'Build_stdout.xml' || $file == 'Build_both.xml') {
                $fields[] = 'stdoutput';
            }
            if ($file == 'Build_stderr.xml' || $file == 'Build_both.xml') {
                $fields[] = 'stderror';
            }

            $this->get($this->url . "/api/v1/viewBuildError.php?buildid=" . $this->BuildId);
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            foreach ($jsonobj['errors'] as $error) {
                foreach ($fields as $field) {
                    if ($error[$field] != $this->Expected) {
                        $this->fail("Expected $this->Expected for $file :: $field, found " . $error[$field]);
                        $this->cleanup();
                        return 1;
                    }
                }
            }
            // Delete the build.
            $this->removeBuild();
        }
        $this->cleanup();
    }

    private function removeBuild()
    {
        if ($this->BuildId > 0) {
            remove_build($this->BuildId);
            $this->BuildId = 0;
        }
    }

    public function cleanup()
    {
        // Restore our configuration and delete the build that we created.
        $this->removeLineFromConfig($this->ConfigLine);
        $this->removeBuild();
    }
}
