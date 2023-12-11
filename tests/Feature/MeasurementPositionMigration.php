<?php

namespace Tests\Feature;

use CDash\Model\Project;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MeasurementPositionMigration extends TestCase
{
    /**
     * Test case for the migration that adds the `position` column to
     * the `measurement` table.
     *
     * @return void
     */
    public function testMeasurementPositionMigration()
    {
        Artisan::call('migrate:fresh', [
            '--force' => true]);

        // Rollback the relevant migration.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2021_09_23_124054_add_measurement_order.php',
            '--force' => true]);

        // Verify that worked.
        $this::assertFalse(Schema::hasColumn('measurement', 'position'));

        $project1 = new Project();
        $project1->Name = 'testMeasurementPositionMigration1';
        $project1->Save();

        $project2 = new Project();
        $project2->Name = 'testMeasurementPositionMigration2';
        $project2->Save();

        // Populate some data to migrate.
        $base_measurement = [
            'projectid'    => $project1->Id,
            'name'         => 'a',
        ];
        $measurement1 = $base_measurement;

        $measurement2 = $base_measurement;
        $measurement2['name'] = 'c';

        $measurement3 = $base_measurement;
        $measurement3['name'] = 'b';

        $measurement4 = $base_measurement;
        $measurement4['projectid'] = $project2->Id;

        $measurement5 = $base_measurement;
        $measurement5['projectid'] = $project2->Id;
        $measurement5['name'] = 'c';

        $measurement6 = $base_measurement;
        $measurement6['projectid'] = $project2->Id;
        $measurement6['name'] = 'b';

        DB::table('measurement')->insert(
            [$measurement1, $measurement2, $measurement3, $measurement4, $measurement5, $measurement6]);

        // Run the migrations under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2021_09_23_124054_add_measurement_order.php',
            '--force' => true]);

        // Verify results.
        $this::assertEquals(6, DB::table('measurement')->count());
        $expected_measurements = [
            [
                'projectid' => $project1->Id,
                'name' => 'a',
                'position' => 1,
            ],
            [
                'projectid' => $project1->Id,
                'name' => 'b',
                'position' => 2,
            ],
            [
                'projectid' => $project1->Id,
                'name' => 'c',
                'position' => 3,
            ],
            [
                'projectid' => $project2->Id,
                'name' => 'a',
                'position' => 1,
            ],
            [
                'projectid' => $project2->Id,
                'name' => 'b',
                'position' => 2,
            ],
            [
                'projectid' => $project2->Id,
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
        $this::assertFalse(Schema::hasColumn('measurement', 'position'));

        Artisan::call('migrate:fresh', [
            '--force' => true]);

        $project1->Delete();
        $project2->Delete();
    }
}
