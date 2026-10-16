<?php

namespace Tests\Feature;

use App\Enums\BuildGroupType;
use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use CDash\Model\BuildGroup as LegacyBuildGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * Builds are only ever assigned to daily groups.  Dynamic groups compute their builds from their
 * rules when the dashboard is shown.
 */
class DynamicBuildGroupTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private User $admin;
    private Site $site;
    private BuildGroup $nightly;
    private BuildGroup $latest;
    private Build $build;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->admin = User::factory()->adminUser()->create();
        $this->site = Site::factory()->create();
        $this->nightly = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();

        $this->latest = BuildGroup::factory()->for($this->project)->create(['type' => BuildGroupType::LATEST]);
        $this->latest->positions()->create([
            'position' => 4,
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
        ]);

        $this->build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
            'type' => 'Nightly',
            'starttime' => '2025-01-15 12:00:00',
        ]);
        $this->build->buildGroups()->attach($this->nightly);
    }

    protected function tearDown(): void
    {
        $_GET = $_POST = $_REQUEST = [];
        unset($_SERVER['REQUEST_METHOD']);

        parent::tearDown();
    }

    private function assertNothingAssignedToDynamicGroup(): void
    {
        self::assertTrue($this->nightly->builds()->whereKey($this->build->id)->exists());
        self::assertSame(0, $this->latest->builds()->count());
        self::assertSame(0, $this->latest->rules()->count());
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return TestResponse<Response>
     */
    private function postToLegacyBuildGroupApi(array $data): TestResponse
    {
        // The legacy API reads its parameters directly from the superglobals.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
        $_REQUEST = ['projectid' => $this->project->id] + $data;
        return $this->actingAs($this->admin)->post('/api/v1/buildgroup.php');
    }

    public function testCannotMoveBuildIntoDynamicGroup(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/build.php', [
            'buildid' => $this->build->id,
            'expected' => 0,
            'newgroupid' => $this->latest->id,
        ])->assertBadRequest();

        $this->assertNothingAssignedToDynamicGroup();
    }

    public function testCannotMarkBuildAsExpectedInDynamicGroup(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/build.php', [
            'buildid' => $this->build->id,
            'expected' => 1,
            'groupid' => $this->latest->id,
        ])->assertBadRequest();

        $this->assertNothingAssignedToDynamicGroup();
    }

    public function testCannotMoveExpectedBuildIntoDynamicGroup(): void
    {
        $rule = $this->nightly->rules()->create([
            'buildtype' => 'Nightly',
            'buildname' => $this->build->name,
            'siteid' => $this->site->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);

        $this->actingAs($this->admin)->postJson('/api/v1/expectedbuild.php', [
            'siteid' => $this->site->id,
            'groupid' => $this->nightly->id,
            'name' => $this->build->name,
            'type' => 'Nightly',
            'newgroupid' => $this->latest->id,
        ])->assertBadRequest();

        self::assertSame($this->nightly->id, $rule->refresh()->groupid);
        $this->assertNothingAssignedToDynamicGroup();
    }

    // buildgroup.php declares functions, so it can only be loaded once per process.
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCannotMoveBuildsIntoDynamicGroupFromManagePage(): void
    {
        $this->postToLegacyBuildGroupApi([
            'builds' => [['id' => $this->build->id]],
            'group' => ['id' => $this->latest->id],
        ])->assertBadRequest();

        $this->assertNothingAssignedToDynamicGroup();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCannotDefineWildcardRuleForDynamicGroup(): void
    {
        $this->postToLegacyBuildGroupApi([
            'group' => ['id' => $this->latest->id],
            'nameMatch' => $this->build->name,
            'type' => 'Nightly',
        ])->assertBadRequest();

        $this->assertNothingAssignedToDynamicGroup();
    }

    public function testDeletingGroupDoesNotMoveItsBuildsIntoDynamicGroup(): void
    {
        // The deleted group's builds go to the group named after their type, if there is one.
        $this->latest->update(['name' => 'Custom']);
        $custom_group = BuildGroup::factory()->for($this->project)->create();
        $custom_build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
            'type' => 'Custom',
        ]);
        $custom_build->buildGroups()->attach($custom_group);

        $legacy_group = new LegacyBuildGroup();
        $legacy_group->SetId($custom_group->id);
        self::assertTrue($legacy_group->Delete());

        $experimental = $this->project->buildgroups()->where('name', 'Experimental')->firstOrFail();
        self::assertSame([$experimental->id], $custom_build->buildGroups()->pluck('buildgroup.id')->all());
    }

    public function testDashboardOnlyOffersDailyGroupsAsMoveTargets(): void
    {
        // Also show the build in the dynamic group.
        $this->latest->rules()->create([
            'buildtype' => '',
            'buildname' => $this->build->name,
            'siteid' => 0,
            'starttime' => Carbon::create(1980),
        ]);

        // The legacy API reads its parameters directly from the superglobals.
        $_GET = $_REQUEST = ['project' => $this->project->name, 'date' => '2025-01-15'];
        $response = $this->get('/api/v1/index.php')->assertOk();

        self::assertEqualsCanonicalizing(
            ['Nightly', 'Continuous', 'Experimental'],
            $response->collect('all_buildgroups')->pluck('name')->all(),
        );
        self::assertEquals(
            ['Nightly' => 'Daily', $this->latest->name => 'Latest'],
            $response->collect('buildgroups')->pluck('type', 'name')->all(),
        );
    }
}
