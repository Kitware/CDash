<?php

namespace Tests\Feature;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testReturnsNotFoundGivenNoImageId()
    {
        $response = $this->get('/image');
        $response->assertStatus(404);
    }

    public function testReturnsNotFoundGivenNonExistantImageId()
    {
        $response = $this->get('/image/10000000000001');
        $response->assertStatus(404);
    }

    public function testOldUriReturnsRedirect()
    {
        $response = $this->get('/displayImage.php?imgid=1');
        $response->assertStatus(301)
            ->assertLocation('/image/1');
    }

    public function testImageReturnsImageGivenExistentImageId()
    {

        $response = $this->get('/image/1');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/gif');
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
    }
}
