<?php

namespace Tests\Feature;

use Artisan;
use Tests\TestCase;

class TestSchemaMigration extends TestCase
{
    /**
     * Test case for the migration of test, build2test, etc. tables.
     *
     * @return void
     */
    public function testMigrationOfTestTables()
    {
        Artisan::call('migrate:fresh', [
            '--force' => true]);

        // Rollback some migrations to drop the relevant tables.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2020_02_17_112005_reformat_test_data.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2020_02_17_111951_add_test_output_table.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2019_09_30_111055_create_build2test_table.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2019_09_30_111055_create_label2test_table.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2019_09_30_111055_create_test_table.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2019_09_30_111055_create_test2image_table.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2019_09_30_111055_create_testmeasurement_table.php',
            '--force' => true]);

        // Make sure they're really gone.
        $this->assertFalse(\Schema::hasTable('build2test'));
        $this->assertFalse(\Schema::hasTable('label2test'));
        $this->assertFalse(\Schema::hasTable('test'));
        $this->assertFalse(\Schema::hasTable('test2image'));
        $this->assertFalse(\Schema::hasTable('testmeasurement'));
        $this->assertFalse(\Schema::hasTable('testoutput'));

        // Recreate testing tables with original schema using migrations.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2019_09_30_111055_create_build2test_table.php',
            '--force' => true]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2019_09_30_111055_create_label2test_table.php',
            '--force' => true]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2019_09_30_111055_create_test_table.php',
            '--force' => true]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2019_09_30_111055_create_test2image_table.php',
            '--force' => true]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2019_09_30_111055_create_testmeasurement_table.php',
            '--force' => true]);

        // Make sure they exist now.
        $this->assertTrue(\Schema::hasTable('build2test'));
        $this->assertTrue(\Schema::hasTable('label2test'));
        $this->assertTrue(\Schema::hasTable('test'));
        $this->assertTrue(\Schema::hasTable('test2image'));
        $this->assertTrue(\Schema::hasTable('testmeasurement'));

        // Populate some data.
        $base_test = [
            'projectid' => 1,
            'crc32'     => 123,
            'name'      => 'a test',
            'path'      => '/tmp',
            'command'   => 'ls',
            'details'   => 'Completed',
            'output'    => '0'
        ];
        $test1 = $base_test;

        $test2 = $base_test;
        $test2['projectid'] = 2;

        $test3 = $base_test;
        $test3['crc32'] = 456;
        $test3['output'] = '0 0 0';

        $test4 = $base_test;
        $test4['crc32'] = '789';
        $test4['name'] = 'another test';
        $test4['output'] = 'something else';

        \DB::table('test')->insert([$test1, $test2, $test3, $test4]);

        $base_buildtest = [
            'buildid' => 1,
            'testid' => 1,
            'status' => 'passed',
            'time' => 1.23,
            'timemean' => 0.00,
            'timestd' => 0.00,
            'timestatus' => 0,
            'newstatus' => 1
        ];

        $buildtest1 = $base_buildtest;
        $buildtest2 = $base_buildtest;
        $buildtest2['testid'] = 2;
        $buildtest3 = $base_buildtest;
        $buildtest3['testid'] = 3;
        $buildtest4 = $base_buildtest;
        $buildtest4['testid'] = 4;
        \DB::table('build2test')->insert([$buildtest1, $buildtest2, $buildtest3, $buildtest4]);

        $base_testlabel = [
            'labelid' => 1,
            'buildid' => 1,
            'testid' => 1
        ];
        $testlabel1 = $base_testlabel;
        $testlabel2 = $base_testlabel;
        $testlabel2['testid'] = 2;
        $testlabel3 = $base_testlabel;
        $testlabel3['testid'] = 3;
        $testlabel4 = $base_testlabel;
        $testlabel4['testid'] = 4;
        \DB::table('label2test')->insert([$testlabel1, $testlabel2, $testlabel3, $testlabel4]);

        $base_testimage = [
            'imgid' => 1,
            'testid' => 1,
            'role' => 'BaseImage'
        ];
        $testimage1 = $base_testimage;
        $testimage2 = $base_testimage;
        $testimage2['testid'] = 2;
        $testimage2['role'] = 'DifferenceImage';
        $testimage3 = $base_testimage;
        $testimage3['testid'] = 3;
        $testimage3['role'] = 'TestImage';
        $testimage4 = $base_testimage;
        $testimage4['testid'] = 4;
        \DB::table('test2image')->insert([$testimage1, $testimage2, $testimage3, $testimage4]);

        $base_testmeasurement = [
            'testid' => 1,
            'name' => 'WallTime',
            'type' => 'numeric/double',
            'value' => 0.1
        ];
        $testmeasurement1 = $base_testmeasurement;
        $testmeasurement2 = $base_testmeasurement;
        $testmeasurement2['testid'] = 2;
        $testmeasurement3 = $base_testmeasurement;
        $testmeasurement3['testid'] = 3;
        $testmeasurement4 = $base_testmeasurement;
        $testmeasurement4['testid'] = 4;
        \DB::table('testmeasurement')->insert([$testmeasurement1, $testmeasurement2, $testmeasurement3, $testmeasurement4]);

        // Run the migrations under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2020_02_17_111951_add_test_output_table.php',
            '--force' => true]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2020_02_17_112005_reformat_test_data.php',
            '--force' => true]);

        // Verify results.
        $this->assertEquals(\DB::table('test')->count(), 3);
        $expected_tests = [
            [
                'name' => 'a test',
                'projectid' => 1,
            ],
            [
                'name' => 'a test',
                'projectid' => 2,
            ],
            [
                'name' => 'another test',
                'projectid' => 1,
            ],
        ];
        foreach ($expected_tests as $expected_test) {
            $this->assertDatabaseHas('test', $expected_test);
        }

        $this->assertEquals(\DB::table('testoutput')->count(), 4);
        $expected_testoutputs = [
            [
                'crc32' => 123,
                'path' => '/tmp',
                'command' => 'ls',
                'output' => '0'
            ],
            [
                'crc32' => 456,
                'path' => '/tmp',
                'command' => 'ls',
                'output' => '0 0 0'
            ],
            [
                'crc32' => 789,
                'path' => '/tmp',
                'command' => 'ls',
                'output' => 'something else'
            ],
        ];
        foreach ($expected_testoutputs as $expected_testoutput) {
            $this->assertDatabaseHas('testoutput', $expected_testoutput);
        }

        foreach (['build2test', 'label2test', 'test2image', 'testmeasurement'] as $table) {
            $this->assertEquals(\DB::table($table)->count(), 4);
            $this->assertDatabaseHas($table, ['outputid' => 1]);
            $this->assertDatabaseHas($table, ['outputid' => 2]);
            $this->assertDatabaseHas($table, ['outputid' => 3]);
            $this->assertDatabaseHas($table, ['outputid' => 4]);
        }

        // Test rollback too.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2020_02_17_112005_reformat_test_data.php',
            '--force' => true]);
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2020_02_17_111951_add_test_output_table.php',
            '--force' => true]);
        $this->assertFalse(\Schema::hasTable('testoutput'));
        $this->assertDatabaseHas('test', $test1);
        $this->assertDatabaseHas('test', $test2);
        $this->assertDatabaseHas('test', $test3);
        $this->assertDatabaseHas('test', $test4);

        $this->assertDatabaseHas('build2test', $buildtest1);
        $this->assertDatabaseHas('build2test', $buildtest2);
        $this->assertDatabaseHas('build2test', $buildtest3);
        $this->assertDatabaseHas('build2test', $buildtest4);

        $this->assertDatabaseHas('label2test', $testlabel1);
        $this->assertDatabaseHas('label2test', $testlabel2);
        $this->assertDatabaseHas('label2test', $testlabel3);
        $this->assertDatabaseHas('label2test', $testlabel4);

        $this->assertDatabaseHas('test2image', $testimage1);
        $this->assertDatabaseHas('test2image', $testimage2);
        $this->assertDatabaseHas('test2image', $testimage3);
        $this->assertDatabaseHas('test2image', $testimage4);

        $this->assertDatabaseHas('testmeasurement', $testmeasurement1);
        $this->assertDatabaseHas('testmeasurement', $testmeasurement2);
        $this->assertDatabaseHas('testmeasurement', $testmeasurement3);
        $this->assertDatabaseHas('testmeasurement', $testmeasurement4);

        Artisan::call('migrate:fresh', [
            '--force' => true]);
    }
}
