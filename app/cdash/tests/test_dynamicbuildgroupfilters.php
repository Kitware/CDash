<?php

require_once __DIR__ . '/cdash_test_case.php';

use App\Models\Project;
use App\Models\Site;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use Illuminate\Support\Facades\DB;

class DynamicBuildGroupFiltersTestCase extends KWWebTestCase
{
    private const BUILD_NAME = 'dynamic-group-filter-build';
    private const PROJECT_NAME = 'DynamicBuildGroupFiltersProject';

    public function testGroupNameFiltersUseDisplayedGroup(): void
    {
        $projectid = $this->createProject(['Name' => self::PROJECT_NAME]);

        $site = Site::create(['name' => 'dynamic-group-filter-site']);
        $build = new Build();
        $build->Name = self::BUILD_NAME;
        $build->ProjectId = $projectid;
        $build->SiteId = $site->id;
        $build->SetStamp('20090223-0710-Nightly');
        $build->StartTime = '2009-02-23 07:10:00';
        $this->assertTrue($build->AddBuild());

        $dynamic_group = new BuildGroup();
        $dynamic_group->SetProjectId($projectid);
        $dynamic_group->SetName('latest results');
        $dynamic_group->SetType('Latest');
        $dynamic_group->Save();

        DB::table('build2grouprule')->insert([
            'groupid' => $dynamic_group->GetId(),
            'buildname' => $build->Name,
            'siteid' => $site->id,
            'parentgroupid' => $build->GroupId,
            'starttime' => '1980-01-01 00:00:00',
            'endtime' => '1980-01-01 00:00:00',
        ]);

        $this->assertGroupFilterReturnsOnly('Nightly', self::BUILD_NAME);
        $this->assertGroupFilterReturnsOnly('latest results', self::BUILD_NAME);
        $this->assertGroupAndBuildFilterReturnsNoGroups('latest results', 'other-build');

        $dynamic_group->Delete();
        remove_project_builds($projectid);
        Project::findOrFail((int) $projectid)->delete();
        $site->delete();
    }

    /** @return list<array{name: string}> */
    private function getFilteredBuildGroups(string $group_name, string $build_name): array
    {
        $filter = http_build_query([
            'filtercount' => 2,
            'filtercombine' => 'and',
            'showfilters' => 1,
            'field1' => 'groupname',
            'compare1' => 61,
            'value1' => $group_name,
            'field2' => 'buildname',
            'compare2' => 61,
            'value2' => $build_name,
        ]);
        $this->get(
            $this->url . '/api/v1/index.php?project=' . self::PROJECT_NAME
            . "&date=2009-02-23&$filter"
        );
        $content = $this->getBrowser()->getContent();
        /** @var array{buildgroups: list<array{name: string}>} $response */
        $response = json_decode($content, true);

        return $response['buildgroups'];
    }

    private function assertGroupFilterReturnsOnly(string $group_name, string $build_name): void
    {
        $buildgroups = $this->getFilteredBuildGroups($group_name, $build_name);

        $this->assertTrue(
            count($buildgroups) > 0,
            "Expected group-name filter to return '$group_name'"
        );
        foreach ($buildgroups as $buildgroup_response) {
            $this->assertEqual($group_name, $buildgroup_response['name']);
        }
    }

    private function assertGroupAndBuildFilterReturnsNoGroups(
        string $group_name,
        string $build_name,
    ): void {
        $this->assertEqual([], $this->getFilteredBuildGroups($group_name, $build_name));
    }
}
