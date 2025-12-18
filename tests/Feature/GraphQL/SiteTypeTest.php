<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class SiteTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;
    use DatabaseTruncation;

    /**
     * @var array<Project>
     */
    private array $projects;

    /**
     * @var array<User>
     */
    private array $users;

    /**
     * @var array<Site>
     */
    private array $sites = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects = [
            'public1' => $this->makePublicProject(),
            'private1' => $this->makePrivateProject(),
        ];

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];

        $user2project_data = [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
        ];

        $this->projects['public1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);

        $this->projects['private1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);
    }

    protected function tearDown(): void
    {
        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->sites as $site) {
            $site->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testBasicFieldAccess(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => Str::uuid()->toString(),
            'ip' => '8.8.8.8',
            'latitude' => '12.3',
            'longitude' => '3.21',
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query ($id: ID!) {
                project(id: $id) {
                    sites {
                        edges {
                            node {
                                id
                                name
                                ip
                                latitude
                                longitude
                            }
                        }
                    }
                }
            }
        ', ['id' => $this->projects['public1']->id])->assertExactJson([
            'data' => [
                'project' => [
                    'sites' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $this->sites['site1']->id,
                                    'name' => $this->sites['site1']->name,
                                    'ip' => $this->sites['site1']->ip,
                                    'latitude' => $this->sites['site1']->latitude,
                                    'longitude' => $this->sites['site1']->longitude,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testTopLevelSiteField(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site2'] = $this->makeSite();

        $this->graphQL('
            query ($id: ID!) {
                site(id: $id) {
                    id
                    name
                }
            }
        ', [
            'id' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'site' => [
                    'id' => (string) $this->sites['site1']->id,
                    'name' => $this->sites['site1']->name,
                ],
            ],
        ]);

        // Make sure it works properly when the site cannot be found
        $this->graphQL('
            query ($id: ID!) {
                site(id: $id) {
                    id
                    name
                }
            }
        ', [
            'id' => 123456789,
        ])->assertExactJson([
            'data' => [
                'site' => null,
            ],
        ]);
    }

    public function testSiteBuildRelationship(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            builds {
                                edges {
                                    node {
                                        site {
                                            name
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'builds' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'site' => [
                                                    'name' => $this->sites['site1']->name,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Insert and retrieve each site information value.
     */
    public function testBasicSiteInformationRelationship(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->sites['site1']->information()->create([
            'processoris64bits' => true,
            'processorvendor' => 'GenuineIntel',
            'processorvendorid' => 'Intel Corporation',
            'processorfamilyid' => 6,
            'processormodelid' => 7,
            'processormodelname' => 'Intel(R) Xeon',
            'processorcachesize' => 123,
            'numberlogicalcpus' => 4,
            'numberphysicalcpus' => 2,
            'totalvirtualmemory' => 2048,
            'totalphysicalmemory' => 15,
            'logicalprocessorsperphysical' => 3,
            'processorclockfrequency' => 2672,
            'description' => 'site 1 description',
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        information {
                                            edges {
                                                node {
                                                    processorIs64Bits
                                                    processorVendor
                                                    processorVendorId
                                                    processorFamilyId
                                                    processorModelId
                                                    processorModelName
                                                    processorCacheSize
                                                    numberLogicalCpus
                                                    numberPhysicalCpus
                                                    totalVirtualMemory
                                                    totalPhysicalMemory
                                                    logicalProcessorsPerPhysical
                                                    processorClockFrequency
                                                    description
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'information' => [
                                                    'edges' => [
                                                        [
                                                            'node' => [
                                                                'processorIs64Bits' => true,
                                                                'processorVendor' => 'GenuineIntel',
                                                                'processorVendorId' => 'Intel Corporation',
                                                                'processorFamilyId' => 6,
                                                                'processorModelId' => 7,
                                                                'processorModelName' => 'Intel(R) Xeon',
                                                                'processorCacheSize' => 123,
                                                                'numberLogicalCpus' => 4,
                                                                'numberPhysicalCpus' => 2,
                                                                'totalVirtualMemory' => 2048,
                                                                'totalPhysicalMemory' => 15,
                                                                'logicalProcessorsPerPhysical' => 3,
                                                                'processorClockFrequency' => 2672,
                                                                'description' => 'site 1 description',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{
     *     array{
     *         array<string,mixed>
     *     }
     * }
     */
    public static function nullabilityTestCases(): array
    {
        return [
            [['processoris64bits' => true]],
            [['processorvendor' => 'GenuineIntel']],
            [['processorvendorid' => 'Intel Corporation']],
            [['processorfamilyid' => 6]],
            [['processormodelid' => 7]],
            [['processormodelname' => 'Intel(R) Xeon']],
            [['processorcachesize' => 123]],
            [['numberlogicalcpus' => 4]],
            [['numberphysicalcpus' => 2]],
            [['totalvirtualmemory' => 2048]],
            [['totalphysicalmemory' => 15]],
            [['logicalprocessorsperphysical' => 3]],
            [['processorclockfrequency' => 2672]],
            [['description' => 'site 1 description']],
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    #[DataProvider('nullabilityTestCases')]
    public function testSiteInformationColumnNullability(array $params): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->sites['site1']->information()->create($params);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        information {
                                            edges {
                                                node {
                                                    processorIs64Bits
                                                    processorVendor
                                                    processorVendorId
                                                    processorFamilyId
                                                    processorModelId
                                                    processorModelName
                                                    processorCacheSize
                                                    numberLogicalCpus
                                                    numberPhysicalCpus
                                                    totalVirtualMemory
                                                    totalPhysicalMemory
                                                    logicalProcessorsPerPhysical
                                                    processorClockFrequency
                                                    description
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'information' => [
                                                    'edges' => [
                                                        [
                                                            'node' => [
                                                                'processorIs64Bits' => $params['processoris64bits'] ?? null,
                                                                'processorVendor' => $params['processorvendor'] ?? null,
                                                                'processorVendorId' => $params['processorvendorid'] ?? null,
                                                                'processorFamilyId' => $params['processorfamilyid'] ?? null,
                                                                'processorModelId' => $params['processormodelid'] ?? null,
                                                                'processorModelName' => $params['processormodelname'] ?? null,
                                                                'processorCacheSize' => $params['processorcachesize'] ?? null,
                                                                'numberLogicalCpus' => $params['numberlogicalcpus'] ?? null,
                                                                'numberPhysicalCpus' => $params['numberphysicalcpus'] ?? null,
                                                                'totalVirtualMemory' => $params['totalvirtualmemory'] ?? null,
                                                                'totalPhysicalMemory' => $params['totalphysicalmemory'] ?? null,
                                                                'logicalProcessorsPerPhysical' => $params['logicalprocessorsperphysical'] ?? null,
                                                                'processorClockFrequency' => $params['processorclockfrequency'] ?? null,
                                                                'description' => $params['description'] ?? null,
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSiteInformationTimestampDefault(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->sites['site1']->information()->create();

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $result = $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            sites {
                                edges {
                                    node {
                                        information {
                                            edges {
                                                node {
                                                    timestamp
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ');

        // Assert that the resulting timestamp is correct +- one minute to account for
        // delay between creation and query, as well as any clock drift between the
        // database and web servers.
        $result_timestamp = Carbon::parse($result['data']['projects']['edges'][0]['node']['sites']['edges'][0]['node']['information']['edges'][0]['node']['timestamp']);
        self::assertGreaterThan(Carbon::now('UTC')->subMinute(), $result_timestamp);
        self::assertLessThan(Carbon::now('UTC')->addMinute(), $result_timestamp);
    }

    public function testMultipleSiteInformation(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->sites['site1']->information()->createMany([
            [
                'description' => 'site 1 information 1',
            ],
            [
                'description' => 'site 1 information 2',
            ],
            [
                'description' => 'site 1 information 3',
            ],
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        information {
                                            edges {
                                                node {
                                                    description
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'information' => [
                                                    'edges' => [
                                                        [
                                                            'node' => [
                                                                'description' => 'site 1 information 3',
                                                            ],
                                                        ],
                                                        [
                                                            'node' => [
                                                                'description' => 'site 1 information 2',
                                                            ],
                                                        ],
                                                        [
                                                            'node' => [
                                                                'description' => 'site 1 information 1',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNoSiteInformationReturnsEmptyArray(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        information {
                                            edges {
                                                node {
                                                    description
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'information' => [
                                                    'edges' => [],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMostRecentSiteInformation(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        // We want to be explicit about creating these in order, so we can't use createMany
        $this->sites['site1']->information()->create(['description' => 'site 1 information 1']);
        $this->sites['site1']->information()->create(['description' => 'site 1 information 2']);
        $this->sites['site1']->information()->create(['description' => 'site 1 information 3']);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        mostRecentInformation {
                                            description
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'mostRecentInformation' => [
                                                    'description' => 'site 1 information 3',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMostRecentSiteInformationReturnsNull(): void
    {
        $this->sites['site1'] = $this->makeSite([
            'name' => 'site1',
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            sites {
                                edges {
                                    node {
                                        name
                                        mostRecentInformation {
                                            description
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                                'mostRecentInformation' => null,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * This test isn't intended to be a complete test of the GraphQL filtering
     * capability, but rather a quick smoke check to verify that the most basic
     * filters work for the sites relation, and that extra information is not leaked.
     */
    public function testBasicSiteFiltering(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->sites['site2'] = $this->makeSite();
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site2']->id,
        ]);
        $this->projects['private1']->builds()->create([
            'name' => 'build3',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site2']->id,
        ]);

        $this->sites['site3'] = $this->makeSite();
        $this->projects['private1']->builds()->create([
            'name' => 'build4',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site3']->id,
        ]);

        $this->actingAs($this->users['normal'])->graphQL("
            query {
                projects {
                    edges {
                        node {
                            name
                            sites(filters: {
                                any: [
                                    {
                                        eq: {
                                            name: \"{$this->sites['site1']->name}\"
                                        }
                                    },
                                    {
                                        eq: {
                                            name: \"{$this->sites['site3']->name}\"
                                        }
                                    }
                                ]
                            }) {
                                edges {
                                    node {
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ")->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site1']->name,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'sites' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => $this->sites['site3']->name,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNoSiteMaintainers(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query($projectid: ID) {
                project(id: $projectid) {
                    name
                    sites {
                        edges {
                            node {
                                name
                                maintainers {
                                    edges {
                                        node {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'projectid' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'name' => $this->projects['public1']->name,
                    'sites' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => $this->sites['site1']->name,
                                    'maintainers' => [
                                        'edges' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testSiteMaintainer(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->sites['site1']->maintainers()->attach($this->users['normal']);

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query($projectid: ID) {
                project(id: $projectid) {
                    name
                    sites {
                        edges {
                            node {
                                name
                                maintainers {
                                    edges {
                                        node {
                                            id
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'projectid' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'name' => $this->projects['public1']->name,
                    'sites' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => $this->sites['site1']->name,
                                    'maintainers' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $this->users['normal']->id,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterSiteMaintainers(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->sites['site1']->maintainers()->attach($this->users['normal']);
        $this->sites['site1']->maintainers()->attach($this->users['admin']);

        $this->graphQL('
            query($siteid: ID, $userid: ID) {
                site(id: $siteid) {
                    maintainers(filters: {
                        eq: {
                            id: $userid
                        }
                    }) {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
            'userid' => $this->users['normal']->id,
        ])->assertExactJson([
            'data' => [
                'site' => [
                    'maintainers' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $this->users['normal']->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testClaimSiteMutationRejectsUnauthenticatedUser(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->graphQL('
            mutation ($siteid: ID!) {
                claimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'claimSite' => [
                    'user' => null,
                    'site' => null,
                    'message' => 'Authentication required to claim sites.',
                ],
            ],
        ]);
    }

    public function testClaimSiteMutationRejectsInvalidSiteId(): void
    {
        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                claimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => 123456789,
        ])->assertExactJson([
            'data' => [
                'claimSite' => [
                    'user' => null,
                    'site' => null,
                    'message' => 'Requested site not found.',
                ],
            ],
        ]);
    }

    public function testClaimSiteMutationAcceptsValidRequest(): void
    {
        $this->sites['site1'] = $this->makeSite();

        self::assertNotContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                claimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'claimSite' => [
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                    'site' => [
                        'id' => (string) $this->sites['site1']->id,
                    ],
                    'message' => null,
                ],
            ],
        ]);

        self::assertContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));
    }

    public function testClaimSiteMutationAcceptsClaimForPreviouslyClaimedSite(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->maintainers()->attach($this->users['normal']);

        self::assertContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                claimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'claimSite' => [
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                    'site' => [
                        'id' => (string) $this->sites['site1']->id,
                    ],
                    'message' => null,
                ],
            ],
        ]);

        self::assertContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));
    }

    public function testUnclaimSiteMutationRejectsUnauthenticatedUser(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->maintainers()->attach($this->users['normal']);

        $this->graphQL('
            mutation ($siteid: ID!) {
                unclaimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'unclaimSite' => [
                    'user' => null,
                    'site' => null,
                    'message' => 'Authentication required to unclaim sites.',
                ],
            ],
        ]);
    }

    public function testUnclaimSiteMutationRejectsInvalidSiteId(): void
    {
        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                unclaimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => 123456789,
        ])->assertExactJson([
            'data' => [
                'unclaimSite' => [
                    'user' => null,
                    'site' => null,
                    'message' => 'Requested site not found.',
                ],
            ],
        ]);
    }

    public function testUnclaimSiteMutationAcceptsValidRequest(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->maintainers()->attach($this->users['normal']);

        self::assertContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                unclaimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'unclaimSite' => [
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                    'site' => [
                        'id' => (string) $this->sites['site1']->id,
                    ],
                    'message' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));
    }

    public function testUnclaimSiteMutationAcceptsUnclaimWhenNotClaimed(): void
    {
        $this->sites['site1'] = $this->makeSite();

        self::assertNotContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($siteid: ID!) {
                unclaimSite(input: {
                    siteId: $siteid
                }) {
                    user {
                        id
                    }
                    site {
                        id
                    }
                    message
                }
            }
        ', [
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'unclaimSite' => [
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                    'site' => [
                        'id' => (string) $this->sites['site1']->id,
                    ],
                    'message' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['normal']->id, $this->sites['site1']->maintainers()->pluck('id'));
    }
}
