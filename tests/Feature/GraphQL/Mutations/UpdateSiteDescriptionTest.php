<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class UpdateSiteDescriptionTest extends TestCase
{
    use CreatesUsers;
    use CreatesSites;

    private Site $site;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = $this->makeSite();
        $this->user = $this->makeNormalUser();
    }

    protected function tearDown(): void
    {
        $this->site->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function testMutationFailsWhenInvalidSiteId(): void
    {
        self::assertEmpty($this->site->information()->get());

        $this->actingAs($this->user)->graphQL('
            mutation ($siteid: ID!, $description: String!) {
                updateSiteDescription(input: {
                    siteId: $siteid
                    description: $description
                }) {
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => 123456789,
            'description' => Str::uuid()->toString(),
        ])->assertJson([
            'data' => [
                'updateSiteDescription' => [
                    'site' => null,
                    'message' => 'Requested site not found.',
                ],
            ],
        ], true);

        self::assertEmpty($this->site->information()->get());
    }

    public function testMutationFailsWhenInvalidUser(): void
    {
        self::assertEmpty($this->site->information()->get());

        $this->graphQL('
            mutation ($siteid: ID!, $description: String!) {
                updateSiteDescription(input: {
                    siteId: $siteid
                    description: $description
                }) {
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->site->id,
            'description' => Str::uuid()->toString(),
        ])->assertJson([
            'data' => [
                'updateSiteDescription' => [
                    'site' => null,
                    'message' => 'Authentication required to edit site descriptions.',
                ],
            ],
        ], true);

        self::assertEmpty($this->site->information()->get());
    }

    public function testMutationPreservesPreviousInformation(): void
    {
        $this->site->information()->forceCreate([
            'timestamp' => Carbon::create(2020),
            'description' => Str::uuid()->toString(),
            'processorclockfrequency' => 1234,
        ]);

        $new_description = Str::uuid()->toString();

        $this->actingAs($this->user)->graphQL('
            mutation ($siteid: ID!, $description: String!) {
                updateSiteDescription(input: {
                    siteId: $siteid
                    description: $description
                }) {
                    site {
                        id
                        mostRecentInformation {
                            timestamp
                            description
                            processorClockFrequency
                        }
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->site->id,
            'description' => $new_description,
        ])->assertJson([
            'data' => [
                'updateSiteDescription' => [
                    'site' => [
                        'id' => (string) $this->site->id,
                        'mostRecentInformation' => [
                            'timestamp' => $this->site->mostRecentInformation?->timestamp->toIso8601String(),
                            'description' => $new_description,
                            'processorClockFrequency' => 1234,
                        ],
                    ],
                    'message' => null,
                ],
            ],
        ], true);

        $site_information = $this->site->information()->get();
        self::assertCount(2, $site_information);
        self::assertNotEquals($site_information[0]->timestamp ?? null, $site_information[1]->timestamp ?? null);
    }
}
