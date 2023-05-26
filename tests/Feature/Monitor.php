<?php

namespace Tests\Feature;

use App\Enums\ClassicPalette;
use App\Enums\HighContrastPalette;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use LogicException;
use Mockery\Exception\InvalidCountException;
use Tests\Traits\CreatesUsers;
use Tests\TestCase;

class Monitor extends TestCase
{
    use CreatesUsers;

    protected User $normal_user;
    protected User $admin_user;

    protected function setUp() : void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');

        $this->normal_user = $this->makeNormalUser();
        $this->admin_user = $this->makeAdminUser();
    }

    /**
     * @throws InvalidCountException
     * @throws LogicException
     */
    protected function tearDown() : void
    {
        if ($this->normal_user::exists()) {
            $this->normal_user->delete();
        }
        if ($this->admin_user::exists()) {
            $this->admin_user->delete();
        }
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();
        DB::table('successful_jobs')->delete();
        parent::tearDown();
    }

    public function testAccessToMonitor() : void
    {
        // By default, the monitor is not available.

        $response = $this->actingAs($this->admin_user)->get('/monitor');
        $response->assertViewIs('admin.monitor');
        $response->assertSee('only available when QUEUE_CONNECTION=database');

        // Enable the relevant config setting to make the monitor available to admins.
        config(['queue.default' => 'database']);

        $response = $this->actingAs($this->admin_user)->get('/monitor');
        $response->assertViewIs('admin.monitor');
        $response->assertDontSee('only available when QUEUE_CONNECTION=database');
    }

    public function testMonitorAPI() : void
    {
        // Verify that only admins can see this page.
        $this->get('/api/monitor')->assertForbidden();
        $this->actingAs($this->normal_user)->get('/api/monitor')->assertForbidden();

        // Verify default (empty) JSON result.
        $this->actingAs($this->admin_user)->get('/api/monitor')->assertJsonFragment([
            'backlog_length' => 0,
            'backlog_time' => null
        ]);

        // Populate some testing data.
        $now = time();
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '1',
            'attempts' => 0,
            'available_at' => $now,
            'created_at' => $now
        ]);

        DB::table('failed_jobs')->insert([
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '2',
            'exception' => 'problem'
        ]);

        DB::table('successful_jobs')->insert(['filename' => 'Build.xml']);

        unset($_COOKIE['colorblind']);

        // Verify expected API response.
        // This includes verifying that we use the classic color palette by default.
        $response = $this->actingAs($this->admin_user)->getJson('/api/monitor');
        $response->assertJsonFragment([
            'backlog_length' => 1,
            'backlog_time' => 'just now'
        ]);
        $response_json = $response->json();
        $this::assertTrue(array_key_exists('time_chart_data', $response_json));
        $this::assertEquals($response_json['time_chart_data'][0]['key'], 'success');
        $this::assertEquals($response_json['time_chart_data'][0]['color'], ClassicPalette::Success->value);
        $success_values = $response_json['time_chart_data'][0]['values'];
        $last_success_value = $success_values[array_key_last($success_values)][1];
        $this::assertEquals($last_success_value, 1);

        $this::assertEquals($response_json['time_chart_data'][1]['key'], 'fail');
        $this::assertEquals($response_json['time_chart_data'][1]['color'], ClassicPalette::Failure->value);
        $fail_values = $response_json['time_chart_data'][1]['values'];
        $last_fail_value = $fail_values[array_key_last($fail_values)][1];
        $this::assertEquals($last_fail_value, 1);

        // Verify that we use the classic palette when explicitly requested.
        $_COOKIE['colorblind'] = 0;
        $response = $this->actingAs($this->admin_user)->getJson('/api/monitor');
        $response_json = $response->json();
        $this::assertEquals($response_json['time_chart_data'][0]['color'], ClassicPalette::Success->value);
        $this::assertEquals($response_json['time_chart_data'][1]['color'], ClassicPalette::Failure->value);

        // Verify that we use the high contrast palette when requested.
        $_COOKIE['colorblind'] = 1;
        $response = $this->actingAs($this->admin_user)->getJson('/api/monitor');
        $response_json = $response->json();
        $this::assertEquals($response_json['time_chart_data'][0]['color'], HighContrastPalette::Success->value);
        $this::assertEquals($response_json['time_chart_data'][1]['color'], HighContrastPalette::Failure->value);
    }
}
