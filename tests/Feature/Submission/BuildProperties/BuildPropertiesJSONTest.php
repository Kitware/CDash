<?php

namespace Tests\Feature\Submission\BuildProperties;

use App\Models\BuildProperties;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BuildPropertiesJSONTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that the properties in a BuildPropertiesJSON file are stored for the build.
     */
    public function testStoresBuildProperties(): void
    {
        Storage::fake();

        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/BuildProperties/data/properties.json'
        ), 'BuildPropertiesJSON');

        $this->assertNotNull($buildid);
        $this->assertSame([
            'debug' => true,
            'buildtime' => 7.02,
            'files' => ['abc', 'def', 'ghi'],
        ], BuildProperties::findOrFail($buildid)->properties);
    }
}
