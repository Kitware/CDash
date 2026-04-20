<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Models\Build;
use App\Models\Test;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/cdash_test_case.php';

class TruncateOutputTestCase extends KWWebTestCase
{
    protected $ConfigFile;
    protected $Original;
    protected $Expected;
    protected $BuildId;

    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = __DIR__ . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);

        $this->Expected = "The beginning survives\n...\nCDash truncated output because it exceeded 44 characters.\n...\nThis part is preserved\n";
        $this->BuildId = 0;
    }

    public function __destruct()
    {
        file_put_contents($this->ConfigFile, $this->Original);
        $this->removeBuild();
    }

    public function testTruncateOutput(): void
    {
        // Verify that some previously submitted data was truncated as expected.
        $buildtests = DB::select(
            "SELECT build2test.id FROM build2test
            JOIN build ON (build.id = build2test.buildid)
            JOIN project ON (project.id = build.projectid)
            WHERE build.name = 'Win32-MSVC2009' AND
                  build.stamp = '20090223-0100-Nightly' AND
                  project.name = 'EmailProjectExample' AND
                  build2test.testname = 'curl'");
        $buildtestid = $buildtests[0]->id;

        $testOutput = Test::findOrFail((int) $buildtestid)->testOutput->output;
        $expected = 'The rest of the test output was removed since it exceeds the threshold';
        $this->assertTrue(str_contains($testOutput, $expected));

        // Set a limit that will cause our test output to be truncated.
        file_put_contents($this->ConfigFile, "LARGE_TEXT_LIMIT=44\n", FILE_APPEND | LOCK_EX);

        $rep = __DIR__ . '/data/TruncateOutput';
        foreach (['Build_stdout.xml', 'Build_stderr.xml', 'Build_both.xml'] as $file) {
            // Submit our testing data.
            if (!$this->submission('InsightExample', "$rep/$file")) {
                $this->fail("failed to submit $file");
            }

            // Query for the ID of the build that we just created.
            $build = Build::where('name', 'TruncateOutput')->firstOrFail();
            $this->BuildId = $build->id;

            // Verify that the output was properly truncated.
            $fields = [];
            if ($file === 'Build_stdout.xml' || $file === 'Build_both.xml') {
                $fields[] = 'stdoutput';
            }
            if ($file === 'Build_stderr.xml' || $file === 'Build_both.xml') {
                $fields[] = 'stderror';
            }

            foreach ($build->buildErrors()->get() as $error) {
                foreach ($fields as $field) {
                    if ($error->getAttribute($field) !== $this->Expected) {
                        $this->fail("Expected $this->Expected for $file :: $field, found " . $error->getAttribute($field));
                    }
                }
            }
            // Delete the build.
            $this->removeBuild();
        }

        // Test removing suppressed warnings.
        $expected = "[CTest: warning matched] This part survives\n";
        $this->submission('InsightExample', "$rep/Build_suppressed.xml");
        $actual = Build::where('name', 'TruncateOutput')
            ->firstOrFail()
            ->buildErrors()
            ->firstOrFail()
            ->stderror;
        $this->assertEqual($expected, $actual);
    }

    private function removeBuild(): void
    {
        if ($this->BuildId > 0) {
            DatabaseCleanupUtils::removeBuild($this->BuildId);
            $this->BuildId = 0;
        }
    }
}
