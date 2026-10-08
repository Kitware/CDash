<?php

namespace Tests\Feature;

use App\Enums\BuildGroupType;
use App\Models\BuildGroup;
use App\Models\BuildGroupRule;
use App\Models\Project;
use App\Models\Site;
use CDash\Model\Build as LegacyBuild;
use CDash\Model\BuildGroup as LegacyBuildGroup;
use CDash\Model\BuildGroupRule as LegacyBuildGroupRule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * Tests how build group rules are interpreted over time.  A rule without an end time is active.
 */
class BuildGroupRuleTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private Site $site;
    private BuildGroup $nightly;
    private BuildGroup $continuous;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();
        $this->nightly = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        $this->continuous = $this->project->buildgroups()->where('name', 'Continuous')->firstOrFail();
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function createRule(BuildGroup $group, array $attributes): BuildGroupRule
    {
        return $group->rules()->create(array_merge([
            'buildtype' => 'Nightly',
            'siteid' => $this->site->id,
            'starttime' => Carbon::create(1980),
        ], $attributes));
    }

    /**
     * Returns the names of the builds shown in the given group on the given day's dashboard.
     *
     * @return array<string>
     */
    private function getDashboardBuildNames(string $group_name, string $date, bool $expected_and_missing = false): array
    {
        // The legacy API reads its parameters directly from the superglobals.
        $_GET = $_REQUEST = ['project' => $this->project->name, 'date' => $date];
        $response = $this->get('/api/v1/index.php');
        $_GET = $_REQUEST = [];
        $response->assertOk();

        /** @var array<int,array{name: string, builds: array<int,array{buildname: string, expectedandmissing?: int}>}> $buildgroups */
        $buildgroups = $response->json('buildgroups');
        foreach ($buildgroups as $buildgroup) {
            if ($buildgroup['name'] === $group_name) {
                $builds = array_filter($buildgroup['builds'], fn ($build) => isset($build['expectedandmissing']) === $expected_and_missing);
                return array_column($builds, 'buildname');
            }
        }
        return [];
    }

    private function getGroupIdForNewBuild(string $name, string $starttime, string $type = 'Nightly'): int
    {
        $build = new LegacyBuild();
        $build->ProjectId = $this->project->id;
        $build->SiteId = $this->site->id;
        $build->Name = $name;
        $build->Type = $type;
        $build->StartTime = $starttime;
        return (new LegacyBuildGroup())->GetGroupIdFromRule($build);
    }

    public function testDashboardShowsDynamicRowsActiveOnTheViewedDay(): void
    {
        $latest = new LegacyBuildGroup();
        $latest->SetProjectId($this->project->id);
        $latest->SetName('Latest');
        $latest->SetType(BuildGroupType::LATEST);
        $latest->Save();
        $latest = BuildGroup::findOrFail($latest->GetId());

        foreach (['active', 'ended-before', 'ended-after'] as $name) {
            $this->project->builds()->create([
                'name' => $name,
                'uuid' => Str::uuid()->toString(),
                'siteid' => $this->site->id,
                'type' => 'Nightly',
                'starttime' => '2025-01-15 12:00:00',
            ])->buildGroups()->attach($this->nightly);
        }

        $this->createRule($latest, ['buildtype' => '', 'buildname' => 'active', 'siteid' => 0]);
        $this->createRule($latest, ['buildtype' => '', 'buildname' => 'ended-before', 'siteid' => 0, 'endtime' => '2025-01-10 00:00:00']);
        $this->createRule($latest, ['buildtype' => '', 'buildname' => 'ended-after', 'siteid' => 0, 'endtime' => '2025-02-01 00:00:00']);

        self::assertEqualsCanonicalizing(['active', 'ended-after'], $this->getDashboardBuildNames('Latest', '2025-01-15'));
    }

    public function testDashboardShowsActiveExpectedBuildsWhichDidNotSubmit(): void
    {
        $this->createRule($this->nightly, ['buildname' => 'active', 'expected' => 1]);
        $this->createRule($this->nightly, ['buildname' => 'ended-before', 'expected' => 1, 'endtime' => '2025-01-10 00:00:00']);
        $this->createRule($this->nightly, ['buildname' => 'ended-after', 'expected' => 1, 'endtime' => '2025-02-01 00:00:00']);

        self::assertEqualsCanonicalizing(['active', 'ended-after'], $this->getDashboardBuildNames('Nightly', '2025-01-15', true));
    }

    public function testExplicitRulesAssignNewBuildsWhileActive(): void
    {
        $this->createRule($this->continuous, ['buildname' => 'active']);
        $this->createRule($this->continuous, ['buildname' => 'ended', 'endtime' => '2025-01-10 00:00:00']);
        $this->createRule($this->continuous, ['buildname' => 'future', 'starttime' => '2025-02-01 00:00:00']);

        self::assertSame($this->continuous->id, $this->getGroupIdForNewBuild('active', '2025-01-15 12:00:00'));
        self::assertSame($this->continuous->id, $this->getGroupIdForNewBuild('ended', '2025-01-05 12:00:00'));
        self::assertSame($this->nightly->id, $this->getGroupIdForNewBuild('ended', '2025-01-15 12:00:00'));
        self::assertSame($this->nightly->id, $this->getGroupIdForNewBuild('future', '2025-01-15 12:00:00'));
    }

    public function testWildcardRulesAssignNewBuildsWhileActive(): void
    {
        $this->createRule($this->continuous, ['buildname' => '%active%', 'siteid' => -1]);
        $this->createRule($this->continuous, ['buildname' => '%ended%', 'siteid' => -1, 'endtime' => '2025-01-10 00:00:00']);

        self::assertSame($this->continuous->id, $this->getGroupIdForNewBuild('gcc-active-debug', '2025-01-15 12:00:00'));
        self::assertSame($this->continuous->id, $this->getGroupIdForNewBuild('gcc-ended-debug', '2025-01-05 12:00:00'));
        self::assertSame($this->nightly->id, $this->getGroupIdForNewBuild('gcc-ended-debug', '2025-01-15 12:00:00'));
    }

    public function testRulesOnDynamicGroupsDoNotAssignNewBuilds(): void
    {
        $latest = BuildGroup::factory()->for($this->project)->create(['type' => BuildGroupType::LATEST]);
        $this->createRule($latest, ['buildname' => 'explicit']);
        $this->createRule($latest, ['buildname' => '%wildcard%', 'siteid' => -1]);

        self::assertSame($this->nightly->id, $this->getGroupIdForNewBuild('explicit', '2025-01-15 12:00:00'));
        self::assertSame($this->nightly->id, $this->getGroupIdForNewBuild('gcc-wildcard-debug', '2025-01-15 12:00:00'));
    }

    public function testDynamicGroupNamedAfterBuildTypeIsNotTheDefault(): void
    {
        BuildGroup::factory()->for($this->project)->create([
            'name' => 'Custom',
            'type' => BuildGroupType::LATEST,
        ]);
        $experimental = $this->project->buildgroups()->where('name', 'Experimental')->firstOrFail();

        self::assertSame($experimental->id, $this->getGroupIdForNewBuild('custom', '2025-01-15 12:00:00', 'Custom'));
    }

    public function testChangingGroupMovesOnlyTheActiveRule(): void
    {
        $active = $this->createRule($this->nightly, ['buildname' => 'moved', 'expected' => 1]);
        $ended = $this->createRule($this->nightly, ['buildname' => 'moved', 'expected' => 1, 'endtime' => '2025-01-10 00:00:00']);

        $rule = new LegacyBuildGroupRule();
        $rule->GroupId = $this->nightly->id;
        $rule->BuildType = 'Nightly';
        $rule->BuildName = 'moved';
        $rule->SiteId = $this->site->id;
        $rule->ChangeGroup($this->continuous->id);

        self::assertSame($this->continuous->id, $active->refresh()->groupid);
        self::assertSame($this->nightly->id, $ended->refresh()->groupid);
    }

    public function testSoftDeletedRulesAreNoLongerActive(): void
    {
        $rule = new LegacyBuildGroupRule();
        $rule->GroupId = $this->nightly->id;
        $rule->BuildType = 'Nightly';
        $rule->BuildName = 'soft-deleted';
        $rule->SiteId = $this->site->id;

        self::assertTrue($rule->Save());
        self::assertTrue($rule->Exists());
        self::assertFalse($rule->Save());
        self::assertSame(1, $this->nightly->rules()->count());
        self::assertNull($this->nightly->rules()->firstOrFail()->endtime);

        $rule->Delete();

        self::assertFalse($rule->Exists());
        self::assertSame(1, $this->nightly->rules()->count());
        self::assertNotNull($this->nightly->rules()->firstOrFail()->endtime);
        $legacy_group = new LegacyBuildGroup();
        $legacy_group->SetId($this->nightly->id);
        self::assertSame([], $legacy_group->GetRules());
    }
}
