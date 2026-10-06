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

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * @param array<string,string> $params
     */
    private function getIndex(array $params): string
    {
        // The legacy index API reads the superglobals directly.
        $_GET = $_REQUEST = ['project' => $this->project->name] + $params;
        $child_filters = $this->get('/api/v1/index.php')->assertOk()->json('childfilters');
        self::assertIsString($child_filters);
        return $child_filters;
    }

    public function testNoSubProjectFilters(): void
    {
        self::assertSame('', $this->getIndex([]));
    }

    public function testIncludedSubProjectsArePassedToChildFilters(): void
    {
        $child_filters = $this->getIndex([
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

        self::assertSame(
            '{"any":[{"has":{"subProject":{"eq":{"name":"Intrepid2"}}}},{"has":{"subProject":{"eq":{"name":"MueLu"}}}}]}',
            urldecode($child_filters),
        );
    }

    public function testExcludedSubProjectsArePassedToChildFilters(): void
    {
        $child_filters = $this->getIndex([
            'filtercount' => '1',
            'showfilters' => '1',
            'field1' => 'subprojects',
            'compare1' => '92',
            'value1' => 'Intrepid2',
        ]);

        self::assertSame(
            '{"all":[{"has":{"subProject":{"ne":{"name":"Intrepid2"}}}}]}',
            urldecode($child_filters),
        );
    }
}
