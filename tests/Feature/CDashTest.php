<?php
namespace Tests\Feature;

use App\Http\Controllers\CDash;
use Config;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Storage;
use Tests\TestCase;

/**
 * A general place to test the CDash installation on-top of Laravel
 *
 * Class CDashTest
 * @package Tests\Feature
 */
class CDashTest extends TestCase
{
    public function testCDashFilesystemConfigExists()
    {
        $expected = [
            'driver' => 'local',
            'root' => app_path('cdash/public'),
            'visibility' => 'private'
        ];

        $actual = Config::get('filesystems.disks.cdash');
        $this->assertEquals($expected, $actual);
    }

    public function testFilesystemAdapterConfiguredForCDash()
    {
        $cdashfs = Storage::disk('cdash');
        $this->assertInstanceOf(FilesystemAdapter::class, $cdashfs);
    }

    // There are some things to note about the approach to setting up this test.

    // First, consider that we're simply creating an output buffer requiring the
    // file that is being requested then requiring the file, e.g:
    //   ob_start();
    //   require $requested_file;
    //   $content = ob_get_contents()
    //   ob_end();
    // This is all well and good except that a few things can happen when requiring
    // the file that may be both unexpected and problematic. The first problem is this,
    // being a testing framework, needs to output information regarding the tests. PHP
    // believes that this output constitutes headers having already been sent and will
    // die with an error stating that "headers have already been sent". This may be
    // easily fixed by editing the phpunit.xml file and adding the stderr attribute to
    // the root node (phpunit), setting its value to "true", e.g. stderr="true".
    //
    // Secondly any time the exit method is called in our required file, the entire
    // application will quit, which is fine if we're not testing, but problematic
    // otherwise. Output of headers, specifically, `Location` seems to be the primary
    // reason for using the `exit` language construct in CDash so avoid testing scripts
    // (URIs) that want to redirect.

    public function testCDashBasicRequest()
    {
        \URL::forceRootUrl('http://localhost');
        $this->get('/viewProjects.php')
            ->assertStatus(200);
    }

    public function testCDashReturnsNotFoundGivenPathDoesNotExist()
    {
        \URL::forceRootUrl('http://localhost');
        $this->get('/nope-not-a-uri')
            ->assertStatus(404);
    }

    public function testRedirects()
    {
        \URL::forceRootUrl('http://localhost');

        $response = $this->call('GET', '/buildSummary.php', ['buildid' => '2']);
        $response->assertRedirect('/build/2');

        $response = $this->call('GET', '/viewConfigure.php', ['buildid' => '5']);
        $response->assertRedirect('/build/5/configure');
    }

    public function testGetController()
    {
        $uri = '/buildProperties.php?buildid=14';
        $request = Request::create($uri);
        $sut = new CDash($request);

        $expected = 'BuildPropertiesController';
        $actual = $sut->getController();
        $this->assertEquals($expected, $actual);

        $uri = '/login.php';
        $request = Request::create($uri);
        $sut = new CDash($request);

        $this->assertEmpty($sut->getController());
    }

    public function testOverrideLoginField()
    {
        \URL::forceRootUrl('http://localhost');
        Config::set('cdash.login_field', 'User');
        $this->get('/login')
            ->assertSeeText('User:');
    }
}
