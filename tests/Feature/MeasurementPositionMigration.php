<?php

namespace Tests\Feature;

use Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSchemaMigration extends TestCase
{
    use RefreshDatabase;

    /**
     * Test case for the migration that adds the `position` column to
     * the `measurement` table.
     *
     * @return void
     */
    public function testMeasurementPositionMigration()
    {
        // Rollback the relevant migration.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2021_09_23_124054_add_measurement_order.php',
            '--force' => true]);

        // Verify that worked.
        $this->assertFalse(\Schema::hasColumn('measurement', 'position'));

        // Populate some data to migrate.
        $base_measurement = [
            'projectid'    => 1,
            'name'         => 'a',
            'testpage'     => '0',
            'summarypage'  => '0'
        ];
        $measurement1 = $base_measurement;

        $measurement2 = $base_measurement;
        $measurement2['name'] = 'c';

        $measurement3 = $base_measurement;
        $measurement3['name'] = 'b';

        $measurement4 = $base_measurement;
        $measurement4['projectid'] = '2';

        $measurement5 = $base_measurement;
        $measurement5['projectid'] = '2';
        $measurement5['name'] = 'c';

        $measurement6 = $base_measurement;
        $measurement6['projectid'] = '2';
        $measurement6['name'] = 'b';

        \DB::table('measurement')->insert(
            [$measurement1, $measurement2, $measurement3, $measurement4, $measurement5, $measurement6]);

        // Run the migrations under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2021_09_23_124054_add_measurement_order.php',
            '--force' => true]);

        // Verify results.
        $this->assertEquals(\DB::table('measurement')->count(), 6);
        $expected_measurements = [
            [
                'projectid' => 1,
                'name' => 'a',
                'position' => 1,
            ],
            [
                'projectid' => 1,
                'name' => 'b',
                'position' => 2,
            ],
            [
                'projectid' => 1,
                'name' => 'c',
                'position' => 3,
            ],
            [
                'projectid' => 2,
                'name' => 'a',
                'position' => 1,
            ],
            [
                'projectid' => 2,
                'name' => 'b',
                'position' => 2,
            ],
            [
                'projectid' => 2,
                'name' => 'c',
                'position' => 3,
            ],
        ];
        foreach ($expected_measurements as $expected_measurement) {
            $this->assertDatabaseHas('measurement', $expected_measurement);
        }

        // Test rollback again.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2021_09_23_124054_add_measurement_order.php',
            '--force' => true]);
        $this->assertFalse(\Schema::hasColumn('measurement', 'position'));
    }
}
