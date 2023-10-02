<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * This test should ultimately replace test_timeline.php
 */
class Timeline extends TestCase
{
    public function testTimelinePassesForValidDefectType(): void
    {
        $filterdata = json_encode(['pageId' => 'buildProperties.php']);
        Session::put('defecttypes', [
            [
                'name' => 'testpassed',
                'prettyname' => 'Errors',
                'selected' => false,
            ],
        ]);
        $response = $this->get("/api/v1/timeline.php?filterdata=$filterdata&project=InsightExample");
        $response->assertDontSeeText('Invalid defect type')->assertOk();
    }

    public function testTimelineFailsForInvalidDefectType(): void
    {
        $filterdata = json_encode(['pageId' => 'buildProperties.php']);
        Session::put('defecttypes', [
            [
                'name' => 'wrongdefecttype',
                'prettyname' => 'Errors',
                'selected' => false,
            ],
        ]);
        $response = $this->get("/api/v1/timeline.php?filterdata=$filterdata&project=InsightExample");
        $response->assertSeeText('Invalid defect type')->assertBadRequest();
    }
}
