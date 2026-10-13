<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class IndexSubProjectFiltersTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    /**
     * @param array<string,string> $params
     */
    private function getIndex(array $params): string
    {
        // The legacy index API reads the superglobals directly.
        $_GET = $_REQUEST = ['project' => $this->project->name] + $params;
        $query = $this->get('/api/v1/index.php')->assertOk()->json('subprojecttestsquery');
        self::assertIsString($query);
        return $query;
    }

    public function testNoSubProjectFilters(): void
    {
        self::assertSame('', $this->getIndex([]));
    }

    public function testIncludedSubProjectsArePassedToTestsPage(): void
    {
        $query = $this->getIndex([
            'filtercount' => '2',
            'showfilters' => '1',
            'filtercombine' => 'or',
            'field1' => 'subprojects',
            'compare1' => '93',
            'value1' => 'Intrepid2',
            'field2' => 'subprojects',
            'compare2' => '93',
            'value2' => 'MueLu',
        ]);

        self::assertSame('&subprojects[]=Intrepid2&subprojects[]=MueLu', $query);
    }

    public function testExcludedSubProjectsArePassedToTestsPage(): void
    {
        $query = $this->getIndex([
            'filtercount' => '1',
            'showfilters' => '1',
            'field1' => 'subprojects',
            'compare1' => '92',
            'value1' => 'Intrepid2',
        ]);

        self::assertSame('&excludeSubprojects[]=Intrepid2', $query);
    }
}
