<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class IndexLabelFiltersTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private string $foo;
    private string $bar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->foo = Str::uuid()->toString();
        $this->bar = Str::uuid()->toString();

        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
            'type' => 'Nightly',
            'starttime' => '2025-01-15 12:00:00',
            'testfailed' => 0,
            'testpassed' => 4,
        ]);
        $build->buildGroups()->attach($this->project->buildgroups()->where('name', 'Nightly')->firstOrFail());

        foreach ([$this->foo, $this->bar, "{$this->foo} {$this->bar}", Str::uuid()->toString()] as $text) {
            $label = Label::create(['text' => $text]);
            $build->labels()->attach($label);
            $build->tests()->create([
                'testname' => Str::uuid()->toString(),
                'status' => 'passed',
            ])->labels()->attach($label);
        }
    }

    protected function tearDown(): void
    {
        $_GET = $_REQUEST = [];

        parent::tearDown();
    }

    /**
     * @return array<string,array{string,int}>
     */
    public static function filterCombineProvider(): array
    {
        return [
            'and' => ['and', 1],
            'or' => ['or', 3],
            'unrecognized' => ['xor', 1],
        ];
    }

    #[DataProvider('filterCombineProvider')]
    public function testLabelFiltersAreCombined(string $filtercombine, int $expected_passed): void
    {
        // The legacy index API reads the superglobals directly.
        $_GET = $_REQUEST = [
            'project' => $this->project->name,
            'date' => '2025-01-15',
            'filtercount' => '2',
            'showfilters' => '1',
            'filtercombine' => $filtercombine,
            'field1' => 'label',
            'compare1' => '63',
            'value1' => $this->foo,
            'field2' => 'label',
            'compare2' => '63',
            'value2' => $this->bar,
        ];

        /** @var array<int,array{name: string, builds: array<int,array{test: array{pass: int}}>}> $buildgroups */
        $buildgroups = $this->get('/api/v1/index.php')->assertOk()->json('buildgroups');
        $builds = array_column($buildgroups, 'builds', 'name');

        self::assertSame($expected_passed, $builds['Nightly'][0]['test']['pass']);
    }
}
