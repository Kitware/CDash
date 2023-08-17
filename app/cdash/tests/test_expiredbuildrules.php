<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\BuildGroup;

class ExpiredBuildRulesTestCase extends KWWebTestCase
{
    public function testExpiredBuildRules()
    {
        $projectid = get_project_id('InsightExample');

        // Create a latest buildgroup.
        $buildGroup = new BuildGroup();
        $buildGroup->SetProjectId($projectid);
        $buildGroup->SetName('recent results');
        $buildGroup->SetType('Latest');
        $buildGroup->Save();

        // Add an entry to it that's only active during a limited time period.
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO build2grouprule
                (groupid, buildname, siteid, parentgroupid, starttime, endtime)
            VALUES
                (:groupid, :buildname, :siteid, :parentgroupid, :starttime, :endtime)');
        $query_params = [
            ':groupid' => $buildGroup->GetId(),
            ':buildname' => 'Linux-g++-4.1-LesionSizingSandbox_Debug',
            ':siteid' => 0,
            ':parentgroupid' => 0,
            ':starttime' => '2009-02-23 00:00:00',
            ':endtime' => '2009-02-25 00:00:00',
        ];
        $db->execute($stmt, $query_params);

        // Verify that this row appears during the valid time period.
        $this->checkForGroup('2009-02-23', true);

        // Verify that this row does not appear outside of the time period.
        $this->checkForGroup('2009-02-26', false);

        // Clean up.
        $buildGroup->Delete();
    }

    private function checkForGroup($date, $expected)
    {
        $this->get($this->url . "/api/v1/index.php?project=InsightExample&date=$date");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $group_found = false;
        foreach ($jsonobj['buildgroups'] as $buildgroup_response) {
            if ($buildgroup_response['name'] === 'recent results') {
                $group_found = true;
            }
        }
        if ($group_found != $expected) {
            $group_found_str = ($group_found) ? 'true' : 'false';
            $expected_str = ($expected) ? 'true' : 'false';
            $this->fail("Expected $expected_str but found $group_found_str for 'Should group be shown'");
        }
    }
}
