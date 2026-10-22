<?php

namespace Tests\Feature\Submission\Project;

use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class ProjectXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Project.xml files are ignored unless the project name in the file matches.
        $this->project = $this->makePublicProject('SubProjectExample');
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test submitting a sequence of Project.xml files which remove and re-add subprojects and
     * dependencies.  After each file, the project's subprojects and their dependencies should
     * match that file.
     */
    public function testSequence(): void
    {
        foreach (range(1, 5) as $i) {
            $file = base_path("tests/Feature/Submission/Project/data/sequence_$i.xml");

            // Subproject changes are stored with one-second precision, so process each file at a
            // distinct whole second.
            $this->travelTo(now()->startOfSecond()->addSecond());
            $this->makeSubmission($this->project->name, $file);

            $this->assertSame($this->subProjectsInFile($file), $this->subProjectsInProject(), "sequence_$i.xml");
        }
    }

    /**
     * Get the subprojects in a Project.xml file, mapped to their sorted dependencies.  Dependencies
     * on subprojects which don't appear earlier in the file are ignored.
     *
     * @return array<string,list<string>>
     */
    private function subProjectsInFile(string $file): array
    {
        $xml = simplexml_load_file($file);
        $this->assertNotFalse($xml);
        $subprojects = $xml->xpath('SubProject');
        $this->assertIsArray($subprojects);

        $result = [];
        foreach ($subprojects as $subproject) {
            $dependency_elements = $subproject->xpath('Dependency');
            $this->assertIsArray($dependency_elements);
            $dependencies = [];
            foreach ($dependency_elements as $dependency) {
                $dependency_name = (string) $dependency['name'];
                if (array_key_exists($dependency_name, $result)) {
                    $dependencies[] = $dependency_name;
                }
            }
            sort($dependencies);
            $result[(string) $subproject['name']] = $dependencies;
        }
        ksort($result);

        return $result;
    }

    /**
     * Get the project's current subprojects, mapped to their sorted dependencies.
     *
     * @return array<string,list<string>>
     */
    private function subProjectsInProject(): array
    {
        $result = [];
        foreach ($this->project->subprojects()->get() as $subproject) {
            $dependencies = $subproject->children()->get()->map(fn (SubProject $dependency): string => $dependency->name)->all();
            sort($dependencies);
            $result[$subproject->name] = $dependencies;
        }
        ksort($result);

        return $result;
    }
}
