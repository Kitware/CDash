<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use CDash\Model\Image;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class TestImageAccessTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    private function createTestImage(Project $project): Image
    {
        $image = new Image();
        $image->Filename = base_path('app/cdash/tests/data/smile.gif');
        $image->Extension = 'image/gif';
        $image->Checksum = crc32(Str::uuid()->toString());
        $image->Save();

        $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
        ])->testImages()->create([
            'role' => 'TestImage',
            'imgid' => (int) $image->Id,
        ]);

        return $image;
    }

    public function testPublicProjectTestImageVisibleToAnonymousUser(): void
    {
        $image = $this->createTestImage($this->makePublicProject());

        $response = $this->get("/image/{$image->Id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        self::assertSame($image->Data, $response->streamedContent());
    }

    public function testPrivateProjectTestImageNotVisibleToAnonymousUser(): void
    {
        $image = $this->createTestImage($this->makePrivateProject());

        $this->get("/image/{$image->Id}")->assertNotFound();
    }

    public function testPrivateProjectTestImageVisibleToGlobalAdmin(): void
    {
        $image = $this->createTestImage($this->makePrivateProject());

        $this->actingAs(User::factory()->adminUser()->create())
            ->get("/image/{$image->Id}")
            ->assertOk();
    }

    public function testImageWithoutTestNotVisible(): void
    {
        $image = new Image();
        $image->Filename = base_path('app/cdash/tests/data/smile.gif');
        $image->Extension = 'image/gif';
        $image->Checksum = crc32(Str::uuid()->toString());
        $image->Save();

        $this->actingAs(User::factory()->adminUser()->create())
            ->get("/image/{$image->Id}")
            ->assertNotFound();
    }
}
