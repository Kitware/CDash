<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Model\Image;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class DisplayImageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDisplayImage()
    {
        $image = new Image();
        $image->Filename = dirname(__FILE__) . '/data/smile.gif';
        $image->Extension = 'image/gif';
        $image->Checksum = 100;
        $image->Save();

        $project = new Project();
        $project->Name = 'ImageTestProject';
        $project->Public = Project::ACCESS_PUBLIC;
        $project->ImageId = $image->Id;
        $project->Save();

        // Try to access a public image
        $response = $this->get($this->url . '/image/' . $image->Id);
        if ($response === false || $response === true || str_contains($response, 'Not Found')) {
            $project->Delete();
            DB::delete('DELETE FROM image WHERE id=?', [$image->Id]);

            $this->fail('Failed to access image for public project');
            return 1;
        }

        $project->Public = Project::ACCESS_PRIVATE;
        $project->Save();

        $response = $this->get($this->url . '/image/' . $image->Id);
        if ($response === false || $response === true || !str_contains($response, 'Not Found')) {
            $project->Delete();
            DB::delete('DELETE FROM image WHERE id=?', [$image->Id]);

            $this->fail('Access to image for private project granted for anonymous user');
            return 1;
        }

        $project->Delete();
        DB::delete('DELETE FROM image WHERE id=?', [$image->Id]);

        $this->pass('Passed');
    }
}
